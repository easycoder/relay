<?php

/**
 * Search and facets over the published catalogue.
 *
 * GET only, so a filtered view is a URL that can be shared, bookmarked or handed
 * to an agent. `?format=json` returns the same rows as data; the HTML is the
 * no-JavaScript view.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/src/html.php';
require dirname(__DIR__, 2) . '/src/projects.php';
require dirname(__DIR__, 2) . '/src/search.php';

$pdo = db();
$categories = $pdo->query('SELECT slug, name FROM categories ORDER BY sort_order, name')->fetchAll();
$categoryMap = array_column($categories, 'name', 'slug');

$filters = search_filters_from($_GET, $categoryMap);
$results = search_projects($pdo, $filters);

if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $siteUrl = rtrim((string) (relay_config()['site']['url'] ?? ''), '/');

    echo json_encode([
        'query' => $filters,
        'total' => $results['total'],
        'page' => $results['page'],
        'pages' => $results['pages'],
        'projects' => array_map(
            static fn (array $row): array => [
                'slug' => $row['slug'],
                'title' => $row['title'],
                'pitch' => $row['pitch'],
                'status' => $row['status'],
                'arrangement' => $row['arrangement'],
                'category' => $row['category'],
                'category_name' => $row['category_name'],
                'readiness' => (int) $row['readiness'],
                'effort_hours_per_month' => $row['effort_hours_per_month'] !== null
                    ? (int) $row['effort_hours_per_month']
                    : null,
                'licence' => $row['licence'],
                'jurisdiction' => $row['jurisdiction'],
                'keywords' => $row['keywords'],
                'expires_at' => $row['expires_at'],
                'url' => $siteUrl . '/projects/' . rawurlencode((string) $row['slug']) . '/',
            ],
            $results['rows']
        ),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    exit;
}

$content = render_template('find', [
    'filters' => $filters,
    'results' => $results,
    'facets' => search_facet_counts($pdo, $filters),
    'categories' => $categories,
    'statuses' => project_status_options(),
    'arrangements' => arrangement_options(),
    'readiness' => readiness_options(),
    'efforts' => search_effort_options(),
]);

echo render_site_page(
    'Find a project — Relay',
    'Filter projects seeking a next maintainer by category, readiness, effort and keyword.',
    '/find/',
    $content,
    true
);
