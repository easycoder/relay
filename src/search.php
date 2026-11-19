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

/** Default results per page. */
const SEARCH_PER_PAGE_DEFAULT = 10;

/**
 * Page sizes offered on the search page.
 *
 * @return array<int, int>
 */
function search_per_page_options(): array
{
    return [10, 20, 50];
}

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
    $view = $choice($input['view'] ?? 'approved', ['approved', 'pending']);
    $sort = $choice($input['sort'] ?? 'random', ['random', 'recent']);
    $sort = $sort === '' ? 'random' : $sort;

    $perPage = filter_var($input['per_page'] ?? SEARCH_PER_PAGE_DEFAULT, FILTER_VALIDATE_INT);
    $perPage = in_array($perPage, search_per_page_options(), true)
        ? (int) $perPage
        : SEARCH_PER_PAGE_DEFAULT;

    $seed = filter_var($input['seed'] ?? 0, FILTER_VALIDATE_INT);
    $seed = is_int($seed) && $seed > 0 && $seed <= 2147483646 ? $seed : null;

    if ($sort === 'random' && $seed === null) {
        $seed = random_int(1, 2147483646);
    }

    if ($sort !== 'random') {
        $seed = null;
    }

    return [
        'q' => $text($input['q'] ?? ''),
        'view' => $view === '' ? 'approved' : $view,
        'sort' => $sort,
        'per_page' => $perPage,
        'seed' => $seed,
        'stage' => $choice($input['stage'] ?? '', array_keys(stage_options())),
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
    $clauses = [];
    $view = ($filters['view'] ?? 'approved') === 'pending' ? 'pending' : 'approved';

    if ($view === 'pending') {
        // The waiting list: everything a person has submitted, whether or not
        // it has been reviewed. Rejected and expired rows are not waiting.
        $clauses = [
            "p.review_status = 'pending'",
            "p.status NOT IN ('draft', 'archived')",
            '(p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP())',
        ];
    } else {
        $clauses = [
            "p.review_status = 'approved'",
            "p.status NOT IN ('draft', 'archived')",
            'p.published_at IS NOT NULL',
            'p.published_at <= UTC_TIMESTAMP()',
            '(p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP())',
        ];
    }

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

    if ($skip !== 'stage' && ($filters['stage'] ?? '') !== '') {
        $clauses[] = 'p.stage = ?';
        $params[] = $filters['stage'];
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
 * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int, sort: string, seed: int|null}
 */
function search_projects(PDO $pdo, array $filters): array
{
    $where = search_where($filters);

    $perPage = (int) ($filters['per_page'] ?? SEARCH_PER_PAGE_DEFAULT);

    if (!in_array($perPage, search_per_page_options(), true)) {
        $perPage = SEARCH_PER_PAGE_DEFAULT;
    }

    $count = $pdo->prepare(
        "SELECT COUNT(*) FROM projects p WHERE {$where['sql']}"
    );
    $count->execute($where['params']);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min((int) $filters['page'], $pages);
    $offset = ($page - 1) * $perPage;

    $sort = ($filters['sort'] ?? 'random') === 'recent' ? 'recent' : 'random';

    if ($sort === 'recent') {
        $order = 'COALESCE(p.published_at, p.created_at) DESC, p.id DESC';
    } else {
        $seed = (int) ($filters['seed'] ?? 0);

        if ($seed < 1) {
            $seed = random_int(1, 2147483646);
        }

        // RAND(seed) is stable for the same seed, so page 2 continues the same
        // shuffle instead of repeating or skipping entries.
        $order = 'RAND(' . $seed . ')';
    }

    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name, u.display_name AS owner_name,
                (SELECT COUNT(*) FROM project_likes l WHERE l.project_id = p.id) AS like_count
         FROM projects p
         JOIN categories c ON c.slug = p.category
         JOIN users u ON u.id = p.owner_id
         WHERE {$where['sql']}
         ORDER BY {$order}
         LIMIT " . $perPage . ' OFFSET ' . $offset
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

    return [
        'rows' => $rows,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
        'sort' => $sort,
        'seed' => $filters['seed'] ?? null,
    ];
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
        'stage' => $countBy('stage', 'p.stage'),
        'category' => $countBy('category', 'p.category'),
        'status' => $countBy('status', 'p.status'),
        'arrangement' => $countBy('arrangement', 'p.arrangement'),
        'readiness' => $countBy('readiness', 'p.readiness'),
        'effort' => $effort,
        'keywords' => array_slice($keywords, 0, 12, true),
    ];
}
