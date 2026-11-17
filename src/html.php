<?php

/**
 * HTML helpers shared by the build script and its templates.
 *
 * Everything dynamic is escaped here or at the point it enters a template.
 * The Markdown renderer escapes first, so it is safe to echo its output.
 */

declare(strict_types=1);

const RELAY_REPO_URL = 'https://github.com/easycoder/relay';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function inline_markdown(string $text): string
{
    // $text has already been escaped by render_markdown().
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text) ?? $text;
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;

    $text = preg_replace_callback(
        '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
        static function (array $matches): string {
            return '<a href="' . $matches[2] . '" rel="noopener">' . $matches[1] . '</a>';
        },
        $text
    ) ?? $text;

    return $text;
}

/**
 * Render the safe subset of Markdown used by Adoption Briefs and guides:
 * ## and ### headings, ordered and unordered lists, paragraphs, **bold**,
 * `inline code` and https links.
 *
 * $headingOffset lets a brief sit under its own h2: pass 1 to turn ## into h3
 * and ### into h4.
 */
function render_markdown(?string $markdown, int $headingOffset = 0): string
{
    if ($markdown === null || trim($markdown) === '') {
        return '';
    }

    $h2 = min(6, 2 + max(0, $headingOffset));
    $h3 = min(6, 3 + max(0, $headingOffset));

    $lines = preg_split('/\R/', e($markdown)) ?: [];
    $html = '';
    $listType = null;

    $closeList = static function () use (&$html, &$listType): void {
        if ($listType !== null) {
            $html .= "</{$listType}>\n";
            $listType = null;
        }
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            $closeList();
            continue;
        }

        if (preg_match('/^###\s+(.+)$/', $trimmed, $matches)) {
            $closeList();
            $html .= '<h' . $h3 . '>' . inline_markdown($matches[1]) . '</h' . $h3 . ">\n";
        } elseif (preg_match('/^##\s+(.+)$/', $trimmed, $matches)) {
            $closeList();
            $html .= '<h' . $h2 . '>' . inline_markdown($matches[1]) . '</h' . $h2 . ">\n";
        } elseif (preg_match('/^[-*]\s+(.+)$/', $trimmed, $matches)) {
            if ($listType !== 'ul') {
                $closeList();
                $html .= "<ul>\n";
                $listType = 'ul';
            }
            $html .= '<li>' . inline_markdown($matches[1]) . "</li>\n";
        } elseif (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $matches)) {
            if ($listType !== 'ol') {
                $closeList();
                $html .= "<ol>\n";
                $listType = 'ol';
            }
            $html .= '<li>' . inline_markdown($matches[1]) . "</li>\n";
        } else {
            $closeList();
            $html .= '<p>' . inline_markdown($trimmed) . "</p>\n";
        }
    }

    $closeList();

    return $html;
}

function render_template(string $template, array $vars): string
{
    $path = dirname(__DIR__) . '/templates/' . $template . '.php';

    if (!is_file($path)) {
        throw new RuntimeException("Template not found: {$template}");
    }

    extract($vars, EXTR_SKIP);

    ob_start();
    require $path;
    $output = ob_get_clean();

    return $output === false ? '' : $output;
}

/**
 * Render a page with the site layout. Used by the interactive pages; the
 * generated catalogue pages use their own renderPage closure in build.php.
 */
function render_site_page(
    string $title,
    string $description,
    string $canonicalPath,
    string $content,
    bool $noindex = false
): string {
    $siteUrl = rtrim((string) (relay_config()['site']['url'] ?? ''), '/');

    // The interactive pages carry CSRF tokens and session state. Never let a
    // browser reuse a cached copy with a token from an older session.
    header('Cache-Control: no-store');

    $currentUser = function_exists('current_user') && function_exists('db')
        ? current_user()
        : null;

    return render_template('layout', [
        'title' => $title,
        'description' => $description,
        'canonical' => $siteUrl . $canonicalPath,
        'content' => $content,
        'siteUrl' => $siteUrl,
        'noindex' => $noindex,
        'currentUser' => $currentUser,
    ]);
}

function status_label(?string $status): string
{
    $labels = [
        'draft' => 'Draft',
        'seeking-successor' => 'Seeking successor',
        'seeking-co-maintainer' => 'Seeking co-maintainer',
        'seeking-buyer' => 'Seeking buyer',
        'temporary-steward' => 'Temporary steward',
        'dormant' => 'Dormant',
        'adopted' => 'Adopted',
        'archived' => 'Archived',
    ];

    return $labels[$status ?? ''] ?? ucfirst(str_replace('-', ' ', (string) $status));
}

function review_label(?string $status): string
{
    $labels = [
        'pending' => 'Awaiting review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    return $labels[$status ?? ''] ?? ucfirst((string) $status);
}

function arrangement_label(?string $arrangement): string
{
    $labels = [
        'gift' => 'Gift',
        'sale' => 'Sale',
        'revenue-share' => 'Revenue share',
        'sponsorship' => 'Sponsorship',
        'co-maintenance' => 'Co-maintenance',
        'other' => 'Other',
    ];

    return $labels[$arrangement ?? ''] ?? ucfirst(str_replace('-', ' ', (string) $arrangement));
}

function readiness_label(int $level): string
{
    $labels = [
        0 => 'L0 — Listed',
        1 => 'L1 — Documented',
        2 => 'L2 — Agent-ready',
        3 => 'L3 — Handover-tested',
        4 => 'L4 — Transferred',
    ];

    return $labels[$level] ?? 'L' . $level;
}

function revenue_label(?string $state): string
{
    $labels = [
        'none' => 'None',
        'potential' => 'Potential',
        'proven' => 'Proven',
        'costs-only' => 'Costs only',
    ];

    return $labels[$state ?? ''] ?? ucfirst(str_replace('-', ' ', (string) $state));
}

function user_scope_label(?string $scope): string
{
    $labels = [
        'none' => 'None yet',
        'individuals' => 'Individuals',
        'businesses' => 'Businesses',
        'public-sector' => 'Public sector',
        'mixed' => 'Mixed',
    ];

    return $labels[$scope ?? ''] ?? ucfirst(str_replace('-', ' ', (string) $scope));
}

function asset_label(?string $asset): string
{
    $labels = [
        'repo' => 'Repository',
        'domain' => 'Domain',
        'hosting' => 'Hosting',
        'data' => 'Data',
        'broker' => 'Message broker',
        'mail' => 'Mail',
        'trademark' => 'Trademark',
        'contracts' => 'Contracts',
        'documentation' => 'Documentation',
    ];

    return $labels[$asset ?? ''] ?? ucfirst(str_replace('-', ' ', (string) $asset));
}

function format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('j M Y');
    } catch (Exception) {
        return $value;
    }
}

/**
 * Whole days until a stored UTC date: negative once it has passed.
 */
function days_until(?string $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    $timestamp = strtotime($value . ' UTC');

    if ($timestamp === false) {
        return null;
    }

    return (int) floor(($timestamp - time()) / 86400);
}

function format_money(mixed $amount, ?string $currency): string
{
    if ($amount === null || $amount === '') {
        return '';
    }

    $formatted = number_format((float) $amount, 2);

    return $currency === null || $currency === '' ? $formatted : $formatted . ' ' . $currency;
}
