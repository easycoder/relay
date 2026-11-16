#!/usr/bin/env php
<?php

/**
 * Build the public catalogue from the database.
 *
 * Writes public/home.html, public/projects/index.html, one page per listing,
 * public/learn/ index and guides, public/projects.json, public/sitemap.xml
 * and public/robots.txt.
 *
 * Run on the server, where PHP and the database are available:
 *   php scripts/build.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/src/html.php';

$root = dirname(__DIR__);
$public = $root . '/public';
$siteUrl = rtrim((string) (relay_config()['site']['url'] ?? ''), '/');

if ($siteUrl === '') {
    fwrite(STDERR, "Config is missing site.url.\n");
    exit(1);
}

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(1);
}

$categories = $pdo->query('SELECT slug, name FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);

$guideMeta = require $root . '/content/learn/guides.php';

if (!is_array($guideMeta)) {
    throw new RuntimeException('content/learn/guides.php must return an array.');
}

$projects = $pdo->query(
    "SELECT p.*, u.display_name AS owner_name
     FROM projects p
     JOIN users u ON u.id = p.owner_id
     WHERE p.review_status = 'approved'
       AND p.status NOT IN ('draft', 'archived')
       AND p.published_at IS NOT NULL
       AND p.published_at <= UTC_TIMESTAMP()
       AND (p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP())
     ORDER BY p.published_at DESC, p.id DESC"
)->fetchAll();

$totalProjects = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
$pendingProjects = (int) $pdo->query("SELECT COUNT(*) FROM projects WHERE review_status = 'pending'")->fetchColumn();

$keywordStmt = $pdo->prepare('SELECT keyword FROM project_keywords WHERE project_id = ? ORDER BY keyword');
$skillStmt = $pdo->prepare('SELECT skill FROM project_skills WHERE project_id = ? ORDER BY skill');
$assetStmt = $pdo->prepare('SELECT asset FROM project_assets WHERE project_id = ? ORDER BY asset');
$obligationStmt = $pdo->prepare('SELECT obligation FROM project_obligations WHERE project_id = ? ORDER BY obligation');

foreach ($projects as &$project) {
    $project['category_name'] = $categories[$project['category']] ?? $project['category'];
    $project['is_draft'] = $project['confirmed_at'] === null;
    $project['show_interest'] = true;

    $keywordStmt->execute([$project['id']]);
    $project['keywords'] = $keywordStmt->fetchAll(PDO::FETCH_COLUMN);

    $skillStmt->execute([$project['id']]);
    $project['skills'] = $skillStmt->fetchAll(PDO::FETCH_COLUMN);

    $assetStmt->execute([$project['id']]);
    $project['assets'] = $assetStmt->fetchAll(PDO::FETCH_COLUMN);

    $obligationStmt->execute([$project['id']]);
    $project['obligations'] = $obligationStmt->fetchAll(PDO::FETCH_COLUMN);
}
unset($project);

$write = static function (string $path, string $contents): void {
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Could not create directory: {$dir}");
    }

    // Write beside the target and rename, so a request during the build never
    // sees a half-written page.
    $tmp = $path . '.tmp.' . getmypid();

    if (file_put_contents($tmp, $contents) === false) {
        throw new RuntimeException("Could not write: {$tmp}");
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Could not replace: {$path}");
    }

    echo 'Wrote ' . $path . PHP_EOL;
};

$renderPage = static function (
    string $title,
    string $description,
    string $canonical,
    string $content
) use ($siteUrl): string {
    return render_template('layout', [
        'title' => $title,
        'description' => $description,
        'canonical' => $canonical,
        'content' => $content,
        'siteUrl' => $siteUrl,
    ]);
};

$homeContent = render_template('home', ['projects' => $projects, 'siteUrl' => $siteUrl]);
$write($public . '/home.html', $renderPage(
    'Relay — find the next maintainer',
    'A free, open-source registry and introduction service for projects that need a next maintainer.',
    $siteUrl . '/',
    $homeContent
));

$projectsContent = render_template('projects', ['projects' => $projects, 'siteUrl' => $siteUrl]);
$write($public . '/projects/index.html', $renderPage(
    'Projects seeking a next maintainer — Relay',
    'Projects that need a next maintainer, described well enough to assess.',
    $siteUrl . '/projects/',
    $projectsContent
));

foreach ($projects as $project) {
    $slug = (string) $project['slug'];
    $canonical = $siteUrl . '/projects/' . rawurlencode($slug) . '/';
    $title = $project['title'] . ' — ' . status_label($project['status']);

    $content = render_template('project', ['project' => $project, 'siteUrl' => $siteUrl]);
    $write(
        $public . '/projects/' . $slug . '/index.html',
        $renderPage($title, (string) $project['pitch'], $canonical, $content)
    );
}

$guideCards = [];

foreach ($guideMeta as $guideSlug => $guide) {
    if (!is_array($guide) || !isset($guide['title'], $guide['description'], $guide['markdown'])) {
        throw new RuntimeException("Guide {$guideSlug} is missing title, description or markdown.");
    }

    $markdown = file_get_contents($guide['markdown']);
    if ($markdown === false) {
        throw new RuntimeException("Could not read guide: {$guide['markdown']}");
    }

    $guideSlug = (string) $guideSlug;

    $content = render_template('learn-page', [
        'guide' => $guide,
        'body' => render_markdown($markdown),
        'siteUrl' => $siteUrl,
    ]);

    $write(
        $public . '/learn/' . $guideSlug . '/index.html',
        $renderPage(
            $guide['title'] . ' — Relay',
            $guide['description'],
            $siteUrl . '/learn/' . rawurlencode($guideSlug) . '/',
            $content
        )
    );

    $guideCards[] = [
        'slug' => $guideSlug,
        'title' => $guide['title'],
        'description' => $guide['description'],
    ];
}

$learnContent = render_template('learn-index', ['guides' => $guideCards, 'siteUrl' => $siteUrl]);
$write($public . '/learn/index.html', $renderPage(
    'Learn — Relay',
    'Guides to the parts of continuity that are easy to postpone: handover mechanisms, adoption briefs, dry runs and the people who inherit the work.',
    $siteUrl . '/learn/',
    $learnContent
));

$jsonProjects = array_map(static function (array $project) use ($siteUrl): array {
    return [
        'slug' => $project['slug'],
        'title' => $project['title'],
        'pitch' => $project['pitch'],
        'status' => $project['status'],
        'arrangement' => $project['arrangement'],
        'category' => $project['category'],
        'readiness' => (int) $project['readiness'],
        'effort_hours_per_month' => $project['effort_hours_per_month'] !== null
            ? (int) $project['effort_hours_per_month']
            : null,
        'licence' => $project['licence'],
        'jurisdiction' => $project['jurisdiction'],
        'url' => $siteUrl . '/projects/' . rawurlencode((string) $project['slug']) . '/',
        'updated_at' => $project['updated_at'],
        'expires_at' => $project['expires_at'],
        'confirmed' => $project['confirmed_at'] !== null,
    ];
}, $projects);

$json = json_encode([
    'generated_at' => gmdate('c'),
    'projects' => $jsonProjects,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if ($json === false) {
    throw new RuntimeException('Could not encode projects.json: ' . json_last_error_msg());
}

$write($public . '/projects.json', $json . PHP_EOL);

$xmlEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');

$urls = [$siteUrl . '/', $siteUrl . '/projects/', $siteUrl . '/learn/'];
foreach ($projects as $project) {
    $urls[] = $siteUrl . '/projects/' . rawurlencode((string) $project['slug']) . '/';
}
foreach (array_keys($guideMeta) as $guideSlug) {
    $urls[] = $siteUrl . '/learn/' . rawurlencode((string) $guideSlug) . '/';
}

$sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
$sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
foreach (array_unique($urls) as $url) {
    $sitemap .= '  <url><loc>' . $xmlEscape($url) . '</loc></url>' . PHP_EOL;
}
$sitemap .= '</urlset>' . PHP_EOL;
$write($public . '/sitemap.xml', $sitemap);

$robots = "User-agent: *\n"
    . "Allow: /\n"
    . "Disallow: /login/\n"
    . "Disallow: /submit/\n"
    . "Disallow: /edit/\n"
    . "Disallow: /interest/\n"
    . "Disallow: /interests/\n"
    . "Disallow: /preview/\n"
    . "Disallow: /dashboard/\n"
    . "Disallow: /review/\n"
    . "Disallow: /logout/\n"
    . "Disallow: /session.php\n"
    . "Sitemap: {$siteUrl}/sitemap.xml\n";
$write($public . '/robots.txt', $robots);

echo PHP_EOL . 'Built ' . count($projects) . ' listing' . (count($projects) === 1 ? '' : 's') . '.' . PHP_EOL;

if ($totalProjects > 0 && count($projects) === 0) {
    echo 'Note: ' . $totalProjects . ' project row(s) exist but none are published and current.' . PHP_EOL;
    echo 'Check status, review_status, published_at and expires_at, and that the owner user exists.' . PHP_EOL;
}

if ($pendingProjects > 0) {
    echo 'Note: ' . $pendingProjects . ' project(s) are awaiting human review; run php scripts/review.php list.' . PHP_EOL;
}
