<?php

/**
 * Submission-time diffs for the review page.
 *
 * An owner's edit can be one word or a complete rewrite. The reviewer needs to
 * see what changed, and the safest record of that is the diff computed when the
 * edit is saved — not a comparison made later against a listing that may have
 * moved on. The result is stored as JSON on the revision or on the listing, and
 * rendered as a Changes / Full text tab pair on `/review/`.
 *
 * The tab markup uses radio buttons and labels, so it works without JavaScript.
 */

declare(strict_types=1);

require_once __DIR__ . '/html.php';

/**
 * Strip a submission down to comparable values.
 *
 * `project_form_data()` and `validate_project_submission()` return lists in
 * different shapes (comma strings against arrays), so every value is normalised
 * here before comparison.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function diff_snapshot(array $data): array
{
    $snapshot = [];

    $fields = [
        'title', 'pitch', 'synopsis', 'brief',
        'stage', 'status', 'arrangement', 'category',
        'revenue_state', 'revenue_model', 'monthly_cost', 'currency', 'cost_description',
        'effort_hours_per_month', 'effort_description',
        'user_scope', 'licence', 'readiness',
        'jurisdiction', 'language', 'timezone',
        'repo_url', 'site_url', 'docs_url', 'demo_url',
        'contact_mode', 'public_contact',
    ];

    foreach ($fields as $key) {
        $snapshot[$key] = trim((string) ($data[$key] ?? ''));
    }

    if ($snapshot['monthly_cost'] !== '' && is_numeric($snapshot['monthly_cost'])) {
        $snapshot['monthly_cost'] = (string) (float) $snapshot['monthly_cost'];
    }

    if ($snapshot['effort_hours_per_month'] !== '' && is_numeric($snapshot['effort_hours_per_month'])) {
        $snapshot['effort_hours_per_month'] = (string) (int) $snapshot['effort_hours_per_month'];
    }

    if ($snapshot['readiness'] !== '') {
        $snapshot['readiness'] = (string) (int) $snapshot['readiness'];
    }

    $snapshot['currency'] = strtoupper($snapshot['currency']);

    $snapshot['keywords'] = diff_list($data['keywords'] ?? [], false);
    $snapshot['skills'] = diff_list($data['skills'] ?? [], false);
    $snapshot['assets'] = diff_list($data['assets'] ?? [], false);
    $snapshot['obligations'] = diff_list($data['obligations'] ?? [], true);

    $snapshot['synopsis_ai_drafted'] = !empty($data['synopsis_ai_drafted']);
    $snapshot['brief_ai_drafted'] = !empty($data['brief_ai_drafted']);

    return $snapshot;
}

/**
 * Normalise a tag list or a list of lines.
 *
 * @return array<int, string>
 */
function diff_list(mixed $value, bool $lines = false): array
{
    if (is_array($value)) {
        $items = $value;
    } else {
        $items = $lines
            ? preg_split('/\R/u', (string) $value)
            : preg_split('/[,\n]+/', (string) $value);
        $items = $items === false ? [] : $items;
    }

    $items = array_map(static fn ($item): string => trim((string) $item), $items);

    return array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
}

/**
 * Build the stored diff.
 *
 * @param array<string, mixed> $before
 * @param array<string, mixed> $after
 * @return array{sections: array<int, array<string, mixed>>}
 */
function build_review_diff(array $before, array $after): array
{
    $old = diff_snapshot($before);
    $new = diff_snapshot($after);
    $sections = [];

    foreach (['title' => 'Title', 'pitch' => 'One-line pitch', 'synopsis' => 'Synopsis', 'brief' => 'Brief'] as $key => $label) {
        if ($old[$key] === $new[$key]) {
            continue;
        }

        $lines = compact_diff(diff_text_lines((string) $old[$key], (string) $new[$key]));

        if ($lines !== []) {
            $sections[] = ['label' => $label, 'type' => 'text', 'lines' => $lines];
        }
    }

    $fields = [
        'stage' => 'Stage',
        'status' => 'Status',
        'arrangement' => 'Arrangement',
        'category' => 'Category',
        'revenue_state' => 'Revenue state',
        'revenue_model' => 'Revenue model',
        'monthly_cost' => 'Monthly cost',
        'currency' => 'Currency',
        'cost_description' => 'Cost description',
        'effort_hours_per_month' => 'Effort (hours per month)',
        'effort_description' => 'What consumes the effort',
        'user_scope' => 'Users',
        'licence' => 'Licence',
        'readiness' => 'Readiness',
        'jurisdiction' => 'Jurisdiction',
        'language' => 'Language',
        'timezone' => 'Timezone',
        'repo_url' => 'Repository',
        'site_url' => 'Live site',
        'docs_url' => 'Documentation',
        'demo_url' => 'Demo',
        'contact_mode' => 'Contact mode',
        'public_contact' => 'Public contact',
    ];

    foreach ($fields as $key => $label) {
        if ($old[$key] === $new[$key]) {
            continue;
        }

        $sections[] = [
            'label' => $label,
            'type' => 'field',
            'old' => diff_format_value($key, (string) $old[$key]),
            'new' => diff_format_value($key, (string) $new[$key]),
        ];
    }

    foreach (['keywords' => 'Keywords', 'skills' => 'Skills', 'assets' => 'Assets', 'obligations' => 'Obligations'] as $key => $label) {
        $oldItems = (array) $old[$key];
        $newItems = (array) $new[$key];

        if ($key !== 'obligations') {
            sort($oldItems);
            sort($newItems);
        }

        if ($oldItems === $newItems) {
            continue;
        }

        $sections[] = [
            'label' => $label,
            'type' => 'field',
            'old' => diff_format_list($key, $oldItems),
            'new' => diff_format_list($key, $newItems),
        ];
    }

    foreach ([
        'synopsis_ai_drafted' => 'Synopsis drafted with AI',
        'brief_ai_drafted' => 'Brief drafted with AI',
    ] as $key => $label) {
        if ($old[$key] === $new[$key]) {
            continue;
        }

        $sections[] = [
            'label' => $label,
            'type' => 'field',
            'old' => $old[$key] ? 'Yes' : 'No',
            'new' => $new[$key] ? 'Yes' : 'No',
        ];
    }

    return ['sections' => $sections];
}

function diff_format_value(string $key, string $value): string
{
    if ($value === '') {
        return '(empty)';
    }

    return match ($key) {
        'stage' => stage_label($value),
        'status' => status_label($value),
        'arrangement' => arrangement_label($value),
        'category' => ucwords(str_replace('-', ' ', $value)),
        'revenue_state' => revenue_label($value),
        'user_scope' => user_scope_label($value),
        'readiness' => readiness_label((int) $value),
        'contact_mode' => $value === 'public' ? 'Publish a contact route' : 'Via the site only',
        default => $value,
    };
}

/**
 * @param array<int, string> $items
 */
function diff_format_list(string $key, array $items): string
{
    if ($items === []) {
        return '(none)';
    }

    if ($key === 'assets') {
        return implode(', ', array_map('asset_label', $items));
    }

    return implode(', ', $items);
}

/**
 * A line-level diff, with a prefix/suffix fallback for very long texts.
 *
 * @return array<int, array{op: string, text: string}>
 */
function diff_text_lines(string $old, string $new): array
{
    $oldLines = preg_split('/\R/u', $old);
    $newLines = preg_split('/\R/u', $new);
    $oldLines = $oldLines === false ? [] : array_map(static fn ($line): string => rtrim($line), $oldLines);
    $newLines = $newLines === false ? [] : array_map(static fn ($line): string => rtrim($line), $newLines);

    $m = count($oldLines);
    $n = count($newLines);

    if ($m * $n > 200000) {
        // Keep a long brief responsive. The common case — a change in one place
        // — stays exact; several distant changes become one replaced block.
        $prefix = 0;

        while ($prefix < $m && $prefix < $n && $oldLines[$prefix] === $newLines[$prefix]) {
            $prefix++;
        }

        $suffix = 0;

        while (
            $suffix < $m - $prefix
            && $suffix < $n - $prefix
            && $oldLines[$m - 1 - $suffix] === $newLines[$n - 1 - $suffix]
        ) {
            $suffix++;
        }

        $ops = [];

        for ($i = 0; $i < $prefix; $i++) {
            $ops[] = ['op' => ' ', 'text' => $oldLines[$i]];
        }

        for ($i = $prefix; $i < $m - $suffix; $i++) {
            $ops[] = ['op' => '-', 'text' => $oldLines[$i]];
        }

        for ($j = $prefix; $j < $n - $suffix; $j++) {
            $ops[] = ['op' => '+', 'text' => $newLines[$j]];
        }

        for ($i = $m - $suffix; $i < $m; $i++) {
            $ops[] = ['op' => ' ', 'text' => $oldLines[$i]];
        }

        return $ops;
    }

    $lcs = [];

    for ($i = 0; $i <= $m; $i++) {
        $lcs[$i] = array_fill(0, $n + 1, 0);
    }

    for ($i = $m - 1; $i >= 0; $i--) {
        for ($j = $n - 1; $j >= 0; $j--) {
            $lcs[$i][$j] = $oldLines[$i] === $newLines[$j]
                ? $lcs[$i + 1][$j + 1] + 1
                : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
        }
    }

    $ops = [];
    $i = 0;
    $j = 0;

    while ($i < $m && $j < $n) {
        if ($oldLines[$i] === $newLines[$j]) {
            $ops[] = ['op' => ' ', 'text' => $oldLines[$i]];
            $i++;
            $j++;
        } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
            $ops[] = ['op' => '-', 'text' => $oldLines[$i]];
            $i++;
        } else {
            $ops[] = ['op' => '+', 'text' => $newLines[$j]];
            $j++;
        }
    }

    while ($i < $m) {
        $ops[] = ['op' => '-', 'text' => $oldLines[$i]];
        $i++;
    }

    while ($j < $n) {
        $ops[] = ['op' => '+', 'text' => $newLines[$j]];
        $j++;
    }

    return $ops;
}

/**
 * Keep changed lines and a little context, replacing long unchanged runs with
 * an ellipsis marker.
 *
 * @param array<int, array{op: string, text: string}> $ops
 * @return array<int, array{op: string, text: string}>
 */
function compact_diff(array $ops, int $context = 2): array
{
    $changed = [];

    foreach ($ops as $index => $op) {
        if (($op['op'] ?? ' ') !== ' ') {
            $changed[] = $index;
        }
    }

    if ($changed === []) {
        return [];
    }

    $keep = [];
    $last = count($ops) - 1;

    foreach ($changed as $index) {
        for ($i = max(0, $index - $context); $i <= min($last, $index + $context); $i++) {
            $keep[$i] = true;
        }
    }

    ksort($keep);

    $out = [];
    $previous = null;

    foreach (array_keys($keep) as $index) {
        if ($previous !== null && $index > $previous + 1) {
            $out[] = ['op' => '@', 'text' => '…'];
        }

        $out[] = $ops[$index];
        $previous = $index;
    }

    return $out;
}

/**
 * The stored JSON, or null when there is nothing to show.
 */
function review_diff_to_json(?array $diff): ?string
{
    if ($diff === null || !is_array($diff['sections'] ?? null) || $diff['sections'] === []) {
        return null;
    }

    $json = json_encode($diff, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $json === false ? null : $json;
}

/**
 * Decode a stored diff; null means "no previous version" or "no changes".
 *
 * @return array<string, mixed>|null
 */
function review_diff_from_json(?string $json): ?array
{
    if ($json === null || trim($json) === '') {
        return null;
    }

    $data = json_decode($json, true);

    return is_array($data) && is_array($data['sections'] ?? null) && $data['sections'] !== []
        ? $data
        : null;
}

/**
 * Render the diff itself, safely escaped.
 *
 * @param array<string, mixed> $diff
 */
function render_review_diff(array $diff): string
{
    $sections = $diff['sections'] ?? [];

    if (!is_array($sections) || $sections === []) {
        return '<p class="meta">No changes.</p>';
    }

    $html = '';

    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }

        $label = (string) ($section['label'] ?? '');
        $type = (string) ($section['type'] ?? '');

        if ($type === 'text' && is_array($section['lines'] ?? null)) {
            $html .= '<h4>' . e($label) . '</h4><div class="diff-block">';

            foreach ($section['lines'] as $line) {
                if (!is_array($line)) {
                    continue;
                }

                $op = (string) ($line['op'] ?? ' ');
                $text = (string) ($line['text'] ?? '');

                if ($op === '@') {
                    $html .= '<div class="diff-line diff-skip">…</div>';
                    continue;
                }

                $class = match ($op) {
                    '+' => 'diff-add',
                    '-' => 'diff-remove',
                    default => 'diff-same',
                };
                $prefix = $op === ' ' ? ' ' : $op;

                $html .= '<div class="diff-line ' . $class . '">' . e($prefix . ' ' . $text) . '</div>';
            }

            $html .= '</div>';
        } elseif ($type === 'field') {
            $html .= '<p class="diff-field"><strong>' . e($label) . ':</strong> '
                . '<span class="diff-remove">' . e((string) ($section['old'] ?? '')) . '</span>'
                . ' → '
                . '<span class="diff-add">' . e((string) ($section['new'] ?? '')) . '</span></p>';
        }
    }

    return $html === '' ? '<p class="meta">No changes.</p>' : $html;
}

/**
 * A CSS-only Changes / Full text tab pair. Changes is the default when a diff
 * exists; without a diff the caller keeps the ordinary full-text view.
 *
 * @param array<string, mixed>|null $diff
 */
function render_review_tabs(string $id, string $fullHtml, ?array $diff): string
{
    if ($diff === null || !is_array($diff['sections'] ?? null) || $diff['sections'] === []) {
        return $fullHtml;
    }

    $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id) ?: 'review';
    $diffId = 'tab-' . $safeId . '-diff';
    $fullId = 'tab-' . $safeId . '-full';

    return '<div class="tabs">'
        . '<input type="radio" name="tabs-' . e($safeId) . '" id="' . e($diffId) . '" checked>'
        . '<input type="radio" name="tabs-' . e($safeId) . '" id="' . e($fullId) . '">'
        . '<div class="tab-labels">'
        . '<label for="' . e($diffId) . '">Changes</label>'
        . '<label for="' . e($fullId) . '">Full text</label>'
        . '</div>'
        . '<div class="tab-panels">'
        . '<div class="tab-panel">' . render_review_diff($diff) . '</div>'
        . '<div class="tab-panel">' . $fullHtml . '</div>'
        . '</div></div>';
}
