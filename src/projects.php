<?php

/**
 * Listing options, validation and creation for the submission form.
 *
 * A submission is always inserted with review_status = 'pending'. The public
 * build only includes approved rows, so nothing reaches the catalogue until a
 * person reviews it.
 */

declare(strict_types=1);

/**
 * How many times a listing may be resubmitted inside the rolling window.
 *
 * A retry is any save that puts a listing back in the queue: the first
 * submission goes through /submit/ and is not counted. The cap exists so a bot
 * cannot resubmit endlessly; five a month is generous for a person fixing a
 * rejection.
 */
const RESUBMIT_LIMIT = 5;

/** The window, in days, that RESUBMIT_LIMIT applies over. */
const RESUBMIT_WINDOW_DAYS = 30;

/**
 * How many listings an account may have waiting for review at once.
 *
 * The reviewer is the scarce resource: a queue bound stops one account burying
 * the queue regardless of pacing, and an approval or rejection frees a slot.
 */
const SUBMIT_PENDING_LIMIT = 2;

/** How many new listings an account may create inside the rolling window. */
const SUBMIT_WINDOW_LIMIT = 3;

/** The window, in days, for SUBMIT_WINDOW_LIMIT and the rejection strikes. */
const SUBMIT_WINDOW_DAYS = 30;

/** Rejections inside the window that pause an account's submissions. */
const SUBMIT_STRIKES = 3;

function project_status_options(): array
{
    return [
        'seeking-successor' => 'Seeking successor',
        'seeking-co-maintainer' => 'Seeking co-maintainer',
        'seeking-buyer' => 'Seeking buyer',
        'temporary-steward' => 'Temporary steward',
        'dormant' => 'Dormant',
    ];
}

function arrangement_options(): array
{
    return [
        'gift' => 'Gift',
        'sale' => 'Sale',
        'revenue-share' => 'Revenue share',
        'sponsorship' => 'Sponsorship',
        'co-maintenance' => 'Co-maintenance',
        'other' => 'Other',
    ];
}

function revenue_state_options(): array
{
    return [
        'none' => 'None',
        'potential' => 'Potential',
        'proven' => 'Proven',
        'costs-only' => 'Costs only',
    ];
}

function user_scope_options(): array
{
    return [
        'none' => 'None yet',
        'individuals' => 'Individuals',
        'businesses' => 'Businesses',
        'public-sector' => 'Public sector',
        'mixed' => 'Mixed',
    ];
}

function readiness_options(): array
{
    return [
        0 => 'L0 — Listed',
        1 => 'L1 — Documented',
        2 => 'L2 — Agent-ready',
        3 => 'L3 — Handover-tested',
        4 => 'L4 — Transferred',
    ];
}

function asset_options(): array
{
    return [
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
}

function contact_mode_options(): array
{
    return [
        'via-site' => 'Via the site only',
        'public' => 'Publish a contact route',
    ];
}

function slugify(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    $slug = substr($slug, 0, 160);

    return $slug === '' ? 'project' : $slug;
}

function unique_slug(PDO $pdo, string $title): string
{
    $base = slugify($title);
    $slug = $base;
    $attempt = 1;

    $stmt = $pdo->prepare('SELECT 1 FROM projects WHERE slug = ?');

    while (true) {
        $stmt->execute([$slug]);

        if ($stmt->fetchColumn() === false) {
            return $slug;
        }

        $attempt++;
        $slug = $base . '-' . $attempt;

        if ($attempt > 100) {
            throw new RuntimeException('Could not generate a unique slug.');
        }
    }
}

function split_tags(string $value, int $max = 20, int $maxLength = 100): array
{
    $parts = preg_split('/[,\r\n]+/', $value) ?: [];
    $parts = array_map(static fn (string $part): string => trim($part), $parts);
    $parts = array_filter($parts, static fn (string $part): bool => $part !== '');
    $parts = array_map(static fn (string $part): string => substr($part, 0, $maxLength), $parts);
    $parts = array_values(array_unique($parts));

    return array_slice($parts, 0, $max);
}

function split_lines(string $value, int $max = 20, int $maxLength = 190): array
{
    $parts = preg_split('/\R/', $value) ?: [];
    $parts = array_map(static fn (string $part): string => trim($part), $parts);
    $parts = array_filter($parts, static fn (string $part): bool => $part !== '');
    $parts = array_map(static fn (string $part): string => substr($part, 0, $maxLength), $parts);

    return array_slice(array_values(array_unique($parts)), 0, $max);
}

function form_value(array $old, string $key, string $default = ''): string
{
    $value = $old[$key] ?? $default;

    return is_scalar($value) ? (string) $value : $default;
}

function form_selected(array $old, string $key, string $value, string $default = ''): string
{
    return form_value($old, $key, $default) === (string) $value ? ' selected' : '';
}

function form_checked(array $old, string $key, string $value): string
{
    $values = $old[$key] ?? [];

    if (!is_array($values)) {
        $values = [$values];
    }

    $values = array_filter($values, 'is_scalar');

    return in_array((string) $value, array_map('strval', $values), true) ? ' checked' : '';
}

function validate_project_submission(array $input, PDO $pdo): array
{
    $assetsInput = array_values(array_filter((array) ($input['assets'] ?? []), 'is_scalar'));
    $input = array_map(static fn ($value) => is_array($value) ? '' : $value, $input);
    $input['assets'] = $assetsInput;

    $data = [
        'title' => trim((string) ($input['title'] ?? '')),
        'pitch' => trim((string) ($input['pitch'] ?? '')),
        'synopsis' => trim((string) ($input['synopsis'] ?? '')),
        'brief' => trim((string) ($input['brief'] ?? '')),
        'status' => (string) ($input['status'] ?? ''),
        'arrangement' => (string) ($input['arrangement'] ?? ''),
        'category' => (string) ($input['category'] ?? ''),
        'revenue_state' => (string) ($input['revenue_state'] ?? ''),
        'revenue_model' => trim((string) ($input['revenue_model'] ?? '')),
        'monthly_cost' => trim((string) ($input['monthly_cost'] ?? '')),
        'currency' => strtoupper(trim((string) ($input['currency'] ?? ''))),
        'cost_description' => trim((string) ($input['cost_description'] ?? '')),
        'effort_hours_per_month' => trim((string) ($input['effort_hours_per_month'] ?? '')),
        'effort_description' => trim((string) ($input['effort_description'] ?? '')),
        'user_scope' => (string) ($input['user_scope'] ?? ''),
        'licence' => trim((string) ($input['licence'] ?? '')),
        'readiness' => (string) ($input['readiness'] ?? '0'),
        'jurisdiction' => trim((string) ($input['jurisdiction'] ?? '')),
        'language' => trim((string) ($input['language'] ?? '')),
        'timezone' => trim((string) ($input['timezone'] ?? '')),
        'repo_url' => trim((string) ($input['repo_url'] ?? '')),
        'site_url' => trim((string) ($input['site_url'] ?? '')),
        'docs_url' => trim((string) ($input['docs_url'] ?? '')),
        'demo_url' => trim((string) ($input['demo_url'] ?? '')),
        'contact_mode' => (string) ($input['contact_mode'] ?? 'via-site'),
        'public_contact' => trim((string) ($input['public_contact'] ?? '')),
        'keywords' => split_tags((string) ($input['keywords'] ?? '')),
        'skills' => split_tags((string) ($input['skills'] ?? '')),
        'assets' => array_values(array_intersect(
            array_keys(asset_options()),
            array_map('strval', (array) ($input['assets'] ?? []))
        )),
        'obligations' => split_lines((string) ($input['obligations'] ?? ''), 20, 190),
        'synopsis_ai_drafted' => empty($input['synopsis_ai_drafted']) ? 0 : 1,
        'brief_ai_drafted' => empty($input['brief_ai_drafted']) ? 0 : 1,
    ];

    $errors = [];

    if ($data['title'] === '') {
        $errors['title'] = 'A title is required.';
    }

    if ($data['pitch'] === '') {
        $errors['pitch'] = 'A one-line pitch is required.';
    }

    if ($data['synopsis'] === '') {
        $errors['synopsis'] = 'A synopsis is required.';
    }

    if ($data['brief'] === '') {
        $errors['brief'] = 'An Adoption Brief is required. It can be short, but it must say what a successor inherits.';
    }

    if (!array_key_exists($data['status'], project_status_options())) {
        $errors['status'] = 'Choose a status.';
    }

    if (!array_key_exists($data['arrangement'], arrangement_options())) {
        $errors['arrangement'] = 'Choose an arrangement.';
    }

    if (!array_key_exists($data['revenue_state'], revenue_state_options())) {
        $errors['revenue_state'] = 'Choose a revenue state.';
    }

    if (!array_key_exists($data['user_scope'], user_scope_options())) {
        $errors['user_scope'] = 'Choose a user group.';
    }

    if (!array_key_exists($data['readiness'], readiness_options())) {
        $errors['readiness'] = 'Choose a readiness level.';
    }

    if (!array_key_exists($data['contact_mode'], contact_mode_options())) {
        $errors['contact_mode'] = 'Choose a contact mode.';
    }

    if ($data['category'] !== '') {
        $stmt = $pdo->prepare('SELECT 1 FROM categories WHERE slug = ?');
        $stmt->execute([$data['category']]);

        if ($stmt->fetchColumn() === false) {
            $errors['category'] = 'Choose a category.';
        }
    } else {
        $errors['category'] = 'Choose a category.';
    }

    $limits = [
        'title' => 200,
        'pitch' => 300,
        'synopsis' => 5000,
        'brief' => 30000,
        'revenue_model' => 120,
        'cost_description' => 500,
        'effort_description' => 500,
        'licence' => 64,
        'jurisdiction' => 64,
        'language' => 16,
        'timezone' => 64,
        'repo_url' => 255,
        'site_url' => 255,
        'docs_url' => 255,
        'demo_url' => 255,
        'public_contact' => 255,
    ];

    foreach ($limits as $field => $max) {
        if (strlen($data[$field]) > $max) {
            $errors[$field] = "Keep this under {$max} characters.";
        }
    }

    foreach (['repo_url', 'site_url', 'docs_url', 'demo_url'] as $field) {
        if ($data[$field] === '') {
            continue;
        }

        $scheme = strtolower((string) parse_url($data[$field], PHP_URL_SCHEME));

        if (!filter_var($data[$field], FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            $errors[$field] = 'Enter a valid http or https URL.';
        }
    }

    if ($data['public_contact'] !== ''
        && !filter_var($data['public_contact'], FILTER_VALIDATE_EMAIL)
        && !filter_var($data['public_contact'], FILTER_VALIDATE_URL)
    ) {
        $errors['public_contact'] = 'Enter an email address or an http(s) URL.';
    }

    if ($data['monthly_cost'] !== ''
        && (!is_numeric($data['monthly_cost']) || (float) $data['monthly_cost'] < 0 || (float) $data['monthly_cost'] > 1000000)
    ) {
        $errors['monthly_cost'] = 'Enter a monthly amount, or leave it blank.';
    }

    if ($data['effort_hours_per_month'] !== ''
        && filter_var($data['effort_hours_per_month'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 1000],
        ]) === false
    ) {
        $errors['effort_hours_per_month'] = 'Enter hours per month between 0 and 1000.';
    }

    if ($data['currency'] !== '' && !preg_match('/^[A-Z]{3}$/', $data['currency'])) {
        $errors['currency'] = 'Use a three-letter currency code.';
    }

    if (empty($input['confirm'])) {
        $errors['confirm'] = 'Please confirm that this description is accurate and that you are the owner or an authorised representative.';
    }

    return [$data, $errors];
}

function create_project(PDO $pdo, int $ownerId, array $data): int
{
    $slug = unique_slug($pdo, $data['title']);

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO projects (
                slug, owner_id, title, pitch, synopsis, brief,
                status, review_status, arrangement, category,
                revenue_state, revenue_model, monthly_cost, currency, cost_description,
                effort_hours_per_month, effort_description,
                user_scope, licence, readiness,
                jurisdiction, language, timezone,
                repo_url, site_url, docs_url, demo_url,
                contact_mode, public_contact,
                synopsis_ai_drafted, brief_ai_drafted,
                confirmed_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, 'pending', ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?,
                ?, ?,
                UTC_TIMESTAMP()
            )"
        );

        $stmt->execute([
            $slug,
            $ownerId,
            $data['title'],
            $data['pitch'],
            $data['synopsis'],
            $data['brief'] !== '' ? $data['brief'] : null,
            $data['status'],
            $data['arrangement'],
            $data['category'],
            $data['revenue_state'],
            $data['revenue_model'] !== '' ? $data['revenue_model'] : null,
            $data['monthly_cost'] !== '' ? $data['monthly_cost'] : null,
            $data['currency'] !== '' ? $data['currency'] : null,
            $data['cost_description'] !== '' ? $data['cost_description'] : null,
            $data['effort_hours_per_month'] !== '' ? (int) $data['effort_hours_per_month'] : null,
            $data['effort_description'] !== '' ? $data['effort_description'] : null,
            $data['user_scope'],
            $data['licence'] !== '' ? $data['licence'] : null,
            (int) $data['readiness'],
            $data['jurisdiction'] !== '' ? $data['jurisdiction'] : null,
            $data['language'] !== '' ? $data['language'] : null,
            $data['timezone'] !== '' ? $data['timezone'] : null,
            $data['repo_url'] !== '' ? $data['repo_url'] : null,
            $data['site_url'] !== '' ? $data['site_url'] : null,
            $data['docs_url'] !== '' ? $data['docs_url'] : null,
            $data['demo_url'] !== '' ? $data['demo_url'] : null,
            $data['contact_mode'],
            $data['public_contact'] !== '' ? $data['public_contact'] : null,
            $data['synopsis_ai_drafted'],
            $data['brief_ai_drafted'],
        ]);

        $projectId = (int) $pdo->lastInsertId();

        replace_project_relations($pdo, $projectId, $data);

        $audit = $pdo->prepare(
            "INSERT INTO audit_log (actor_user_id, action, entity_type, entity_id, detail, created_at)
             VALUES (?, 'project.submitted', 'project', ?, ?, UTC_TIMESTAMP())"
        );
        $audit->execute([$ownerId, $projectId, 'Submitted via /submit/']);

        $pdo->commit();

        return $projectId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function project_relations(PDO $pdo, int $projectId): array
{
    $relations = [
        'keywords' => [],
        'skills' => [],
        'assets' => [],
        'obligations' => [],
    ];

    $queries = [
        'keywords' => 'SELECT keyword FROM project_keywords WHERE project_id = ? ORDER BY keyword',
        'skills' => 'SELECT skill FROM project_skills WHERE project_id = ? ORDER BY skill',
        'assets' => 'SELECT asset FROM project_assets WHERE project_id = ? ORDER BY asset',
        'obligations' => 'SELECT obligation FROM project_obligations WHERE project_id = ? ORDER BY obligation',
    ];

    foreach ($queries as $key => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$projectId]);
        $relations[$key] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    return $relations;
}

function project_form_data(array $project, array $relations = []): array
{
    return [
        'title' => (string) ($project['title'] ?? ''),
        'pitch' => (string) ($project['pitch'] ?? ''),
        'synopsis' => (string) ($project['synopsis'] ?? ''),
        'brief' => (string) ($project['brief'] ?? ''),
        'status' => (string) ($project['status'] ?? ''),
        'arrangement' => (string) ($project['arrangement'] ?? ''),
        'category' => (string) ($project['category'] ?? ''),
        'revenue_state' => (string) ($project['revenue_state'] ?? ''),
        'revenue_model' => (string) ($project['revenue_model'] ?? ''),
        'monthly_cost' => ($project['monthly_cost'] ?? null) !== null ? (string) $project['monthly_cost'] : '',
        'currency' => (string) ($project['currency'] ?? ''),
        'cost_description' => (string) ($project['cost_description'] ?? ''),
        'effort_hours_per_month' => ($project['effort_hours_per_month'] ?? null) !== null
            ? (string) $project['effort_hours_per_month']
            : '',
        'effort_description' => (string) ($project['effort_description'] ?? ''),
        'user_scope' => (string) ($project['user_scope'] ?? ''),
        'licence' => (string) ($project['licence'] ?? ''),
        'readiness' => (string) ($project['readiness'] ?? '0'),
        'jurisdiction' => (string) ($project['jurisdiction'] ?? ''),
        'language' => (string) ($project['language'] ?? ''),
        'timezone' => (string) ($project['timezone'] ?? ''),
        'repo_url' => (string) ($project['repo_url'] ?? ''),
        'site_url' => (string) ($project['site_url'] ?? ''),
        'docs_url' => (string) ($project['docs_url'] ?? ''),
        'demo_url' => (string) ($project['demo_url'] ?? ''),
        'contact_mode' => (string) ($project['contact_mode'] ?? 'via-site'),
        'public_contact' => (string) ($project['public_contact'] ?? ''),
        'keywords' => implode(', ', $relations['keywords'] ?? []),
        'skills' => implode(', ', $relations['skills'] ?? []),
        'assets' => $relations['assets'] ?? [],
        'obligations' => implode("\n", $relations['obligations'] ?? []),
        'synopsis_ai_drafted' => !empty($project['synopsis_ai_drafted']) ? '1' : '',
        'brief_ai_drafted' => !empty($project['brief_ai_drafted']) ? '1' : '',
    ];
}

function project_data_to_form(array $data): array
{
    $form = $data;
    $form['keywords'] = implode(', ', (array) ($data['keywords'] ?? []));
    $form['skills'] = implode(', ', (array) ($data['skills'] ?? []));
    $form['obligations'] = implode("\n", (array) ($data['obligations'] ?? []));
    $form['assets'] = (array) ($data['assets'] ?? []);
    $form['synopsis_ai_drafted'] = !empty($data['synopsis_ai_drafted']) ? '1' : '';
    $form['brief_ai_drafted'] = !empty($data['brief_ai_drafted']) ? '1' : '';
    unset($form['confirm']);

    return $form;
}

function replace_project_relations(PDO $pdo, int $projectId, array $data): void
{
    $pdo->prepare('DELETE FROM project_keywords WHERE project_id = ?')->execute([$projectId]);
    $pdo->prepare('DELETE FROM project_skills WHERE project_id = ?')->execute([$projectId]);
    $pdo->prepare('DELETE FROM project_assets WHERE project_id = ?')->execute([$projectId]);
    $pdo->prepare('DELETE FROM project_obligations WHERE project_id = ?')->execute([$projectId]);

    $keywordStmt = $pdo->prepare('INSERT IGNORE INTO project_keywords (project_id, keyword) VALUES (?, ?)');
    foreach ((array) ($data['keywords'] ?? []) as $keyword) {
        $keywordStmt->execute([$projectId, $keyword]);
    }

    $skillStmt = $pdo->prepare('INSERT IGNORE INTO project_skills (project_id, skill) VALUES (?, ?)');
    foreach ((array) ($data['skills'] ?? []) as $skill) {
        $skillStmt->execute([$projectId, $skill]);
    }

    $assetStmt = $pdo->prepare('INSERT IGNORE INTO project_assets (project_id, asset) VALUES (?, ?)');
    foreach ((array) ($data['assets'] ?? []) as $asset) {
        $assetStmt->execute([$projectId, $asset]);
    }

    $obligationStmt = $pdo->prepare('INSERT IGNORE INTO project_obligations (project_id, obligation) VALUES (?, ?)');
    foreach ((array) ($data['obligations'] ?? []) as $obligation) {
        $obligationStmt->execute([$projectId, $obligation]);
    }
}

/**
 * Apply a validated submission or revision payload to a project row and
 * replace its keyword, skill, asset and obligation rows. The caller owns the
 * transaction and decides review_status, published_at and expires_at.
 */
function apply_project_data(PDO $pdo, int $projectId, array $data, ?string $confirmedAt = null): void
{
    $stmt = $pdo->prepare(
        "UPDATE projects SET
            title = ?, pitch = ?, synopsis = ?, brief = ?,
            status = ?, arrangement = ?, category = ?,
            revenue_state = ?, revenue_model = ?, monthly_cost = ?, currency = ?, cost_description = ?,
            effort_hours_per_month = ?, effort_description = ?,
            user_scope = ?, licence = ?, readiness = ?,
            jurisdiction = ?, language = ?, timezone = ?,
            repo_url = ?, site_url = ?, docs_url = ?, demo_url = ?,
            contact_mode = ?, public_contact = ?,
            synopsis_ai_drafted = ?, brief_ai_drafted = ?,
            confirmed_at = COALESCE(?, UTC_TIMESTAMP())
         WHERE id = ?"
    );

    $stmt->execute([
        $data['title'],
        $data['pitch'],
        $data['synopsis'],
        $data['brief'] !== '' ? $data['brief'] : null,
        $data['status'],
        $data['arrangement'],
        $data['category'],
        $data['revenue_state'],
        $data['revenue_model'] !== '' ? $data['revenue_model'] : null,
        $data['monthly_cost'] !== '' ? $data['monthly_cost'] : null,
        $data['currency'] !== '' ? $data['currency'] : null,
        $data['cost_description'] !== '' ? $data['cost_description'] : null,
        $data['effort_hours_per_month'] !== '' ? (int) $data['effort_hours_per_month'] : null,
        $data['effort_description'] !== '' ? $data['effort_description'] : null,
        $data['user_scope'],
        $data['licence'] !== '' ? $data['licence'] : null,
        (int) $data['readiness'],
        $data['jurisdiction'] !== '' ? $data['jurisdiction'] : null,
        $data['language'] !== '' ? $data['language'] : null,
        $data['timezone'] !== '' ? $data['timezone'] : null,
        $data['repo_url'] !== '' ? $data['repo_url'] : null,
        $data['site_url'] !== '' ? $data['site_url'] : null,
        $data['docs_url'] !== '' ? $data['docs_url'] : null,
        $data['demo_url'] !== '' ? $data['demo_url'] : null,
        $data['contact_mode'],
        $data['public_contact'] !== '' ? $data['public_contact'] : null,
        $data['synopsis_ai_drafted'],
        $data['brief_ai_drafted'],
        $confirmedAt,
        $projectId,
    ]);

    replace_project_relations($pdo, $projectId, $data);
}

function create_or_update_revision(PDO $pdo, int $projectId, int $userId, array $data): int
{
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        throw new RuntimeException('Could not encode the revision: ' . json_last_error_msg());
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM project_revisions
         WHERE project_id = ? AND review_status = 'pending'
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$projectId]);
    $revisionId = $stmt->fetchColumn();

    if ($revisionId !== false) {
        $update = $pdo->prepare(
            'UPDATE project_revisions
             SET created_by = ?, payload = ?, created_at = UTC_TIMESTAMP(), note = NULL
             WHERE id = ?'
        );
        $update->execute([$userId, $payload, $revisionId]);

        return (int) $revisionId;
    }

    $insert = $pdo->prepare(
        "INSERT INTO project_revisions (project_id, created_by, payload, review_status, created_at)
         VALUES (?, ?, ?, 'pending', UTC_TIMESTAMP())"
    );
    $insert->execute([$projectId, $userId, $payload]);

    return (int) $pdo->lastInsertId();
}

function pending_revision(PDO $pdo, int $projectId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM project_revisions
         WHERE project_id = ? AND review_status = 'pending'
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$projectId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Listings this account has waiting for review.
 */
function pending_listings(PDO $pdo, int $ownerId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM projects WHERE owner_id = ? AND review_status = 'pending'"
    );
    $stmt->execute([$ownerId]);

    return (int) $stmt->fetchColumn();
}

/**
 * Listings this account has created inside the window.
 */
function new_listings_in_window(PDO $pdo, int $ownerId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM projects
         WHERE owner_id = ?
           AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . SUBMIT_WINDOW_DAYS . ' DAY)'
    );
    $stmt->execute([$ownerId]);

    return (int) $stmt->fetchColumn();
}

/**
 * When the oldest listing in the window ages out, which is when the next one
 * may be created. Null when the account has room now.
 */
function new_listing_slot_at(PDO $pdo, int $ownerId): ?string
{
    $stmt = $pdo->prepare(
        'SELECT DATE_ADD(MIN(created_at), INTERVAL ' . SUBMIT_WINDOW_DAYS . ' DAY)
         FROM projects
         WHERE owner_id = ?
           AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . SUBMIT_WINDOW_DAYS . ' DAY)'
    );
    $stmt->execute([$ownerId]);
    $value = $stmt->fetchColumn();

    return is_string($value) && $value !== '' ? $value : null;
}

/**
 * Rejections this account has collected inside the window.
 *
 * Counted from the audit trail rather than from the listings themselves: a
 * rejected listing that is resubmitted stops being rejected, but the record of
 * the decision should not disappear with it.
 */
function rejections_in_window(PDO $pdo, int $ownerId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM audit_log a
         JOIN projects p ON p.id = a.entity_id
         WHERE a.action = 'project.rejected'
           AND a.entity_type = 'project'
           AND p.owner_id = ?
           AND a.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . SUBMIT_WINDOW_DAYS . ' DAY)"
    );
    $stmt->execute([$ownerId]);

    return (int) $stmt->fetchColumn();
}

/**
 * When a paused account may submit again. Null when it is not paused.
 */
function submission_pause_until(PDO $pdo, int $ownerId): ?string
{
    if (rejections_in_window($pdo, $ownerId) < SUBMIT_STRIKES) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT DATE_ADD(MIN(a.created_at), INTERVAL ' . SUBMIT_WINDOW_DAYS . ' DAY)
         FROM audit_log a
         JOIN projects p ON p.id = a.entity_id
         WHERE a.action = 'project.rejected'
           AND a.entity_type = 'project'
           AND p.owner_id = ?
           AND a.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . SUBMIT_WINDOW_DAYS . ' DAY)"
    );
    $stmt->execute([$ownerId]);
    $value = $stmt->fetchColumn();

    return is_string($value) && $value !== '' ? $value : null;
}

/**
 * How many resubmissions this listing has used inside the window.
 */
function resubmissions_used(PDO $pdo, int $projectId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM project_resubmissions
         WHERE project_id = ?
           AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . RESUBMIT_WINDOW_DAYS . ' DAY)'
    );
    $stmt->execute([$projectId]);

    return (int) $stmt->fetchColumn();
}

/**
 * When the oldest resubmission in the window drops out, which is when the next
 * attempt becomes possible. Null when there is nothing to wait for.
 */
function resubmissions_open_at(PDO $pdo, int $projectId): ?string
{
    $stmt = $pdo->prepare(
        'SELECT DATE_ADD(MIN(created_at), INTERVAL ' . RESUBMIT_WINDOW_DAYS . ' DAY)
         FROM project_resubmissions
         WHERE project_id = ?
           AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . RESUBMIT_WINDOW_DAYS . ' DAY)'
    );
    $stmt->execute([$projectId]);
    $value = $stmt->fetchColumn();

    return is_string($value) && $value !== '' ? $value : null;
}

/**
 * Record a resubmission. One row per retry, so the window can be counted.
 */
function record_resubmission(PDO $pdo, int $projectId, int $userId): void
{
    $pdo->prepare(
        'INSERT INTO project_resubmissions (project_id, user_id, created_at)
         VALUES (?, ?, UTC_TIMESTAMP())'
    )->execute([$projectId, $userId]);
}

function revision_data(array $revision): array
{
    $data = json_decode((string) ($revision['payload'] ?? ''), true);

    return is_array($data) ? $data : [];
}
