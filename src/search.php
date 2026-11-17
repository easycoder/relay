<?php

/**
 * Search and facets over the published catalogue.
 *
 * The static pages are what search engines and agents read; this is the
 * interactive layer over the same rows, filtered in SQL so it can never show a
 * listing the build would not publish. It is plain PHP with a GET form — no
 * JavaScript — and `?format=json` returns the same result set as data, which is
 * the surface an AllSpeak or agent client would read.
 */

declare(strict_types=1);

/** Results per page. */
const SEARCH_PER_PAGE = 12;

/** Effort buckets, in hours per month. */
function search_effort_options(): array
{
    return [
        'low' => 'Up to 2 hours a month',
        'medium' => '3 to 8 hours a month',
        'high' => 'More than 8 hours a month',
    ];
}

/**
 * Clean the query string into a known set of filters.
 *
 * Anything unrecognised is dropped rather than passed to SQL: the facets are
 * closed sets, so an unknown category or readiness is treated as "not asked for".
 *
 * @param array<string, mixed> $input     Usually $_GET.
 * @param array<string, string> $categories Category slug => name, from the database.
 * @return array<string, mixed>
 */
function search_filters_from(array $input, array $categories): array
{
    $text = static function (mixed $value, int $max = 100): string {
        if (!is_scalar($value)) {
            return '';
        }

        return substr(trim((string) $value), 0, $max);
    };

    $choice = static function (mixed $value, array $allowed): string {
        if (!is_scalar($value)) {
            return '';
        }

        $value = (string) $value;

        return in_array($value, $allowed, true) ? $value : '';
    };

    $page = filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT);

    return [
        'q' => $text($input['q'] ?? ''),
        'category' => $choice($input['category'] ?? '', array_map('strval', array_keys($categories))),
        'status' => $choice($input['status'] ?? '', array_keys(project_status_options())),
        'arrangement' => $choice($input['arrangement'] ?? '', array_keys(arrangement_options())),
        'readiness' => $choice($input['readiness'] ?? '', array_map('strval', array_keys(readiness_options()))),
        'effort' => $choice($input['effort'] ?? '', array_keys(search_effort_options())),
        'keyword' => $text($input['keyword'] ?? ''),
        'page' => $page === false || $page < 1 ? 1 : $page,
    ];
}

/**
 * The WHERE clauses for a set of filters, as SQL plus its parameters.
 *
 * Kept free of the database so the clauses can be tested directly. `$skip` names
 * one facet to leave out, which is how each facet counts what its own choice
 * would narrow to.
 *
 * @param array<string, mixed> $filters
 * @return array{sql: string, params: array<int, mixed>}
 */
function search_where(array $filters, string $skip = ''): array
{
    $clauses = [
        "p.review_status = 'approved'",
        "p.status NOT IN ('draft', 'archived')",
        'p.published_at IS NOT NULL',
        'p.published_at <= UTC_TIMESTAMP()',
        '(p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP())',
    ];
    $params = [];

    if ($skip !== 'q' && ($filters['q'] ?? '') !== '') {
        $clauses[] = '(p.title LIKE ? OR p.pitch LIKE ? OR p.synopsis LIKE ? OR p.brief LIKE ?)';
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $filters['q']) . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($skip !== 'category' && ($filters['category'] ?? '') !== '') {
        $clauses[] = 'p.category = ?';
        $params[] = $filters['category'];
    }

    if ($skip !== 'status' && ($filters['status'] ?? '') !== '') {
        $clauses[] = 'p.status = ?';
        $params[] = $filters['status'];
    }

    if ($skip !== 'arrangement' && ($filters['arrangement'] ?? '') !== '') {
        $clauses[] = 'p.arrangement = ?';
        $params[] = $filters['arrangement'];
    }

    if ($skip !== 'readiness' && ($filters['readiness'] ?? '') !== '') {
        $clauses[] = 'p.readiness = ?';
        $params[] = (int) $filters['readiness'];
    }

    if ($skip !== 'effort' && ($filters['effort'] ?? '') !== '') {
        $clauses[] = match ($filters['effort']) {
            'low' => 'p.effort_hours_per_month IS NOT NULL AND p.effort_hours_per_month <= 2',
            'medium' => 'p.effort_hours_per_month BETWEEN 3 AND 8',
            'high' => 'p.effort_hours_per_month > 8',
            default => '1 = 1',
        };
    }

    if ($skip !== 'keyword' && ($filters['keyword'] ?? '') !== '') {
        $clauses[] = 'EXISTS (SELECT 1 FROM project_keywords k WHERE k.project_id = p.id AND k.keyword = ?)';
        $params[] = $filters['keyword'];
    }

    return [
        'sql' => implode("\n       AND ", $clauses),
        'params' => $params,
    ];
}

/**
 * Run a search.
 *
 * @param array<string, mixed> $filters As returned by search_filters_from().
 * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int}
 */
function search_projects(PDO $pdo, array $filters): array
{
    $where = search_where($filters);

    $count = $pdo->prepare(
        "SELECT COUNT(*) FROM projects p WHERE {$where['sql']}"
    );
    $count->execute($where['params']);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / SEARCH_PER_PAGE));
    $page = min((int) $filters['page'], $pages);
    $offset = ($page - 1) * SEARCH_PER_PAGE;

    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name, u.display_name AS owner_name
         FROM projects p
         JOIN categories c ON c.slug = p.category
         JOIN users u ON u.id = p.owner_id
         WHERE {$where['sql']}
         ORDER BY p.published_at DESC, p.id DESC
         LIMIT " . SEARCH_PER_PAGE . ' OFFSET ' . $offset
    );
    $stmt->execute($where['params']);
    $rows = $stmt->fetchAll();

    $keywordStmt = $pdo->prepare('SELECT keyword FROM project_keywords WHERE project_id = ? ORDER BY keyword');

    foreach ($rows as &$row) {
        $row['is_draft'] = $row['confirmed_at'] === null;
        $keywordStmt->execute([$row['id']]);
        $row['keywords'] = $keywordStmt->fetchAll(PDO::FETCH_COLUMN);
    }
    unset($row);

    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
}

/**
 * Counts per facet value, each computed without its own filter so a facet shows
 * what choosing it would lead to rather than only what is already chosen.
 *
 * @param array<string, mixed> $filters
 * @return array<string, array<string, int>>
 */
function search_facet_counts(PDO $pdo, array $filters): array
{
    $countBy = static function (string $facet, string $expression, string $join = '') use ($pdo, $filters): array {
        $where = search_where($filters, $facet);

        $sql = "SELECT {$expression} AS value, COUNT(*) AS n
                FROM projects p {$join}
                WHERE {$where['sql']}
                  AND {$expression} IS NOT NULL
                  AND {$expression} <> ''
                GROUP BY {$expression}
                ORDER BY n DESC, value ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($where['params']);

        $out = [];

        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['value']] = (int) $row['n'];
        }

        return $out;
    };

    $effort = $countBy('effort', 'CASE
            WHEN p.effort_hours_per_month IS NULL THEN NULL
            WHEN p.effort_hours_per_month <= 2 THEN \'low\'
            WHEN p.effort_hours_per_month <= 8 THEN \'medium\'
            ELSE \'high\'
        END');

    $keywords = $countBy('keyword', 'k.keyword', 'JOIN project_keywords k ON k.project_id = p.id');

    return [
        'category' => $countBy('category', 'p.category'),
        'status' => $countBy('status', 'p.status'),
        'arrangement' => $countBy('arrangement', 'p.arrangement'),
        'readiness' => $countBy('readiness', 'p.readiness'),
        'effort' => $effort,
        'keywords' => array_slice($keywords, 0, 12, true),
    ];
}
