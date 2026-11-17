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

/** How many days before expiry an owner is warned. */
const EXPIRY_REMINDER_DAYS = 14;

/** How long an expiry link stays valid: long enough to survive a holiday. */
const EXPIRY_LINK_MINUTES = 30 * 24 * 60;

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

/**
 * Character limits per field, shared by the validator and the AI prompt so the
 * two cannot drift apart.
 *
 * @return array<string, int>
 */
function submission_field_limits(): array
{
    return [
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
}

/**
 * Every field a submission carries, in the order the form asks for them.
 *
 * This is the single description of the shape: the AI prompt is generated from
 * it and the JSON importer reads it, so adding a field here puts it in front of
 * the owner's AI as well.
 *
 * @return array<string, array{label: string, type: string, required: bool, note: string}>
 */
function submission_fields(): array
{
    return [
        'title' => ['label' => 'Title', 'type' => 'string', 'required' => true, 'note' => 'The project name as it should appear in the catalogue.'],
        'pitch' => ['label' => 'One-line pitch', 'type' => 'string', 'required' => true, 'note' => 'One sentence: what it does and who it is for.'],
        'synopsis' => ['label' => 'Synopsis', 'type' => 'text', 'required' => true, 'note' => 'A short plain-English description for someone who has never used it.'],
        'brief' => ['label' => 'Adoption Brief', 'type' => 'markdown', 'required' => true, 'note' => 'Markdown, using the ten sections listed above.'],
        'status' => ['label' => 'Status', 'type' => 'string', 'required' => true, 'note' => 'What the owner is looking for.'],
        'arrangement' => ['label' => 'Arrangement', 'type' => 'string', 'required' => true, 'note' => 'How the project would change hands.'],
        'category' => ['label' => 'Category', 'type' => 'string', 'required' => true, 'note' => 'One category slug from the list above.'],
        'revenue_state' => ['label' => 'Revenue state', 'type' => 'string', 'required' => true, 'note' => 'Whether it earns anything today.'],
        'revenue_model' => ['label' => 'Revenue model', 'type' => 'string', 'required' => false, 'note' => 'How money is made, if it is. Empty when there is none.'],
        'monthly_cost' => ['label' => 'Monthly cost', 'type' => 'number', 'required' => false, 'note' => 'Running cost per month as a bare number (no currency symbol), or null.'],
        'currency' => ['label' => 'Currency', 'type' => 'string', 'required' => false, 'note' => 'Three-letter code such as GBP, or null when there is no cost.'],
        'cost_description' => ['label' => 'Cost description', 'type' => 'string', 'required' => false, 'note' => 'What the running cost consists of.'],
        'effort_hours_per_month' => ['label' => 'Effort', 'type' => 'integer', 'required' => false, 'note' => 'Hours a maintainer needs each month, 0 to 1000, or null.'],
        'effort_description' => ['label' => 'Effort description', 'type' => 'string', 'required' => false, 'note' => 'What that time goes on.'],
        'user_scope' => ['label' => 'Users', 'type' => 'string', 'required' => true, 'note' => 'Who uses it.'],
        'licence' => ['label' => 'Licence', 'type' => 'string', 'required' => false, 'note' => 'The licence the code is under, for example MIT.'],
        'readiness' => ['label' => 'Readiness', 'type' => 'integer', 'required' => true, 'note' => 'One of the readiness levels above, as a number: 0 to 4.'],
        'jurisdiction' => ['label' => 'Jurisdiction', 'type' => 'string', 'required' => false, 'note' => 'Where the owner or the obligations sit, for example GB.'],
        'language' => ['label' => 'Language', 'type' => 'string', 'required' => false, 'note' => 'Primary language of the project, for example en.'],
        'timezone' => ['label' => 'Timezone', 'type' => 'string', 'required' => false, 'note' => 'Where the maintainer is, for example Europe/London.'],
        'repo_url' => ['label' => 'Repository', 'type' => 'url', 'required' => false, 'note' => 'http(s) URL of the repository, or null.'],
        'site_url' => ['label' => 'Live site', 'type' => 'url', 'required' => false, 'note' => 'http(s) URL of the running site, or null.'],
        'docs_url' => ['label' => 'Documentation', 'type' => 'url', 'required' => false, 'note' => 'http(s) URL of the documentation, or null.'],
        'demo_url' => ['label' => 'Demo', 'type' => 'url', 'required' => false, 'note' => 'http(s) URL of a demo, or null.'],
        'contact_mode' => ['label' => 'Contact mode', 'type' => 'string', 'required' => true, 'note' => 'How an adopter reaches the owner.'],
        'public_contact' => ['label' => 'Public contact', 'type' => 'string', 'required' => false, 'note' => 'An email address or http(s) URL to publish, only when contact_mode is public.'],
        'keywords' => ['label' => 'Keywords', 'type' => 'list of strings', 'required' => false, 'note' => 'Up to 20 short keywords, lowercase.'],
        'skills' => ['label' => 'Skills', 'type' => 'list of strings', 'required' => false, 'note' => 'Up to 20 skills a successor needs.'],
        'assets' => ['label' => 'Assets', 'type' => 'list of strings', 'required' => false, 'note' => 'What the owner would hand over, from the asset list above.'],
        'obligations' => ['label' => 'Obligations', 'type' => 'list of strings', 'required' => false, 'note' => 'Up to 20 promises and duties that come with the project, one per item.'],
        'synopsis_ai_drafted' => ['label' => 'Synopsis drafted with AI', 'type' => 'boolean', 'required' => false, 'note' => 'true if an AI drafted the synopsis and the owner has checked it.'],
        'brief_ai_drafted' => ['label' => 'Brief drafted with AI', 'type' => 'boolean', 'required' => false, 'note' => 'true if an AI drafted the brief and the owner has checked it.'],
    ];
}

/**
 * @return array<int, string>
 */
function submission_field_keys(): array
{
    return array_keys(submission_fields());
}

/**
 * Turn a decoded JSON listing into form values.
 *
 * Tolerant on purpose: an AI may return a comma-separated string where a list
 * was asked for, or a boolean where a checkbox is expected. Unknown keys are
 * dropped, and nothing is written — the result only prefills the form.
 *
 * @param array<string, mixed> $json
 * @return array<string, mixed>
 */
function project_json_to_form(array $json): array
{
    $toList = static function (mixed $value): array {
        if (is_array($value)) {
            $items = array_map(
                static fn ($item): string => is_scalar($item) ? trim((string) $item) : '',
                $value
            );
        } elseif (is_scalar($value)) {
            $items = array_map('trim', explode(',', (string) $value));
        } else {
            $items = [];
        }

        return array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
    };

    $form = [];

    foreach (submission_field_keys() as $key) {
        $value = $json[$key] ?? '';

        if (in_array($key, ['keywords', 'skills', 'assets', 'obligations'], true)) {
            $form[$key] = $value;
            continue;
        }

        if (is_bool($value)) {
            $form[$key] = $value ? '1' : '';
            continue;
        }

        $form[$key] = is_scalar($value) ? trim((string) $value) : '';
    }

    $form['keywords'] = implode(', ', $toList($form['keywords']));
    $form['skills'] = implode(', ', $toList($form['skills']));
    $form['obligations'] = implode("\n", $toList($form['obligations']));
    $form['assets'] = array_values(array_intersect(
        array_keys(asset_options()),
        array_map('strval', $toList($json['assets'] ?? []))
    ));
    $form['synopsis_ai_drafted'] = !empty($json['synopsis_ai_drafted']) ? '1' : '';
    $form['brief_ai_drafted'] = !empty($json['brief_ai_drafted']) ? '1' : '';

    return $form;
}

/**
 * Take the brief from an upload or a compressed field, when the form sent one.
 *
 * A long, code-filled brief can make a host's web application firewall reject
 * the whole POST before PHP runs: the content looks like an attack to a pattern
 * matcher. There are two escapes, tried in order:
 *
 *  1. `brief_file` — the brief as a file part, which is how the import sends the
 *     same text successfully on this host;
 *  2. `brief_gz` — the brief gzipped and base64-encoded by the browser, which
 *     has no text at all for a pattern matcher to score.
 *
 * With neither, the textarea posts as normal and nothing here changes.
 *
 * @param array<string, mixed> $files Usually $_FILES.
 * @param array<string, mixed> $post  Usually $_POST; modified in place.
 */
function absorb_brief_upload(array &$post, array $files, int $maxBytes = 131072): void
{
    $file = $files['brief_file'] ?? null;

    if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $tmp = (string) ($file['tmp_name'] ?? '');

        if ($tmp !== '' && is_file($tmp) && (int) ($file['size'] ?? 0) <= $maxBytes) {
            $text = file_get_contents($tmp);

            if (is_string($text) && $text !== '') {
                $post['brief'] = $text;

                return;
            }
        }
    }

    $encoded = $post['brief_gz'] ?? '';

    if (!is_string($encoded) || $encoded === '' || !function_exists('gzdecode')) {
        return;
    }

    $raw = base64_decode($encoded, true);

    if ($raw === false || strlen($raw) > $maxBytes) {
        return;
    }

    $text = @gzdecode($raw);

    if (is_string($text) && $text !== '') {
        $post['brief'] = $text;
    }
}

/**
 * Decode a listing from the text an AI produced.
 *
 * Accepts a Markdown code fence because AI answers often arrive inside one.
 * Throws rather than returning null so the caller can say what was wrong.
 *
 * @return array<string, mixed>
 * @throws InvalidArgumentException
 */
function listing_from_json_text(string $text): array
{
    $text = trim($text);

    if ($text === '') {
        throw new InvalidArgumentException('There was nothing to import.');
    }

    if (str_starts_with($text, '```')) {
        $text = (string) preg_replace('/^```[a-zA-Z]*\s*/', '', $text);
        $text = (string) preg_replace('/```\s*$/', '', $text);
        $text = trim($text);
    }

    $json = json_decode($text, true);

    if (!is_array($json) || array_is_list($json)) {
        throw new InvalidArgumentException('That is not a JSON object.');
    }

    return $json;
}

/**
 * The prompt an owner pastes into their AI, generated from the field
 * definitions so that a change to the listing shape reaches the prompt.
 *
 * @param array<string, string> $categories Category slug => name, from the database.
 */
function submission_prompt(array $categories = []): string
{
    $limits = submission_field_limits();
    $fieldLines = [];

    foreach (submission_fields() as $key => $field) {
        $limit = isset($limits[$key]) ? ', max ' . $limits[$key] . ' characters' : '';
        $fieldLines[] = '- ' . $key . ' (' . $field['type'] . ', '
            . ($field['required'] ? 'required' : 'optional') . $limit . '): ' . $field['note'];
    }

    $optionLines = [];

    foreach ([
        'status' => project_status_options(),
        'arrangement' => arrangement_options(),
        'revenue_state' => revenue_state_options(),
        'user_scope' => user_scope_options(),
        'contact_mode' => contact_mode_options(),
        'assets' => asset_options(),
        'readiness' => readiness_options(),
    ] as $key => $options) {
        $optionLines[] = '- ' . $key . ': ' . implode(' | ', array_keys($options));
    }

    $categoryLines = [];

    foreach ($categories as $slug => $name) {
        $categoryLines[] = '- ' . $slug . ' (' . $name . ')';
    }

    $example = [
        'title' => 'Example project',
        'pitch' => 'One sentence on what it does and who it is for.',
        'synopsis' => 'A short description for someone who has never used it.',
        'brief' => "## What it is\n\nOne paragraph a non-user would understand.\n\n## Who uses it\n\nDescribe the users, or say that there are none yet.\n",
        'status' => 'seeking-successor',
        'arrangement' => 'gift',
        'category' => (string) (array_key_first($categories) ?: 'developer-tools'),
        'revenue_state' => 'costs-only',
        'revenue_model' => '',
        'monthly_cost' => 12,
        'currency' => 'GBP',
        'cost_description' => 'Hosting and mail.',
        'effort_hours_per_month' => 4,
        'effort_description' => 'Support mail and keeping dependencies current.',
        'user_scope' => 'individuals',
        'licence' => 'MIT',
        'readiness' => 1,
        'jurisdiction' => 'GB',
        'language' => 'en',
        'timezone' => 'Europe/London',
        'repo_url' => 'https://example.com/repo',
        'site_url' => null,
        'docs_url' => null,
        'demo_url' => null,
        'contact_mode' => 'via-site',
        'public_contact' => '',
        'keywords' => ['example', 'php'],
        'skills' => ['php', 'mysql'],
        'assets' => ['repo', 'domain'],
        'obligations' => ['Keep the site running.'],
        'synopsis_ai_drafted' => true,
        'brief_ai_drafted' => true,
    ];

    $lines = [
        'You are helping the owner of a software project write a listing for Relay.',
        '',
        'Relay is a free, open-source registry for projects that need a next maintainer.',
        'An adopter, often working with an AI agent, reads the listing to decide whether to',
        'take the project on. Accuracy matters more than persuasion: a listing that',
        'overstates what is there wastes everyones time, and the owner has to confirm every',
        'claim before a person reviews it.',
        '',
        'How to work',
        '- Ask the owner about anything you do not know. Never invent figures, dates,',
        '  licences, URLs, obligations or history.',
        '- Write in plain English, in the owners voice. No marketing, no superlatives.',
        '- The brief is Markdown with these ten sections, in this order:',
        '  1. What it is - one paragraph a non-user would understand.',
        '  2. Who uses it - customers, installers, internal users, nobody yet.',
        '  3. Why it exists - the problem, the history, what would be lost.',
        '  4. How it works - architecture in plain language, naming the files that matter.',
        '  5. How to run, test and deploy it - commands, environments, gotchas.',
        '  6. The estate - repository, domain, hosting, broker, mail, data, trademarks,',
        '     contracts.',
        '  7. The economics - revenue, costs, pricing, who pays, what is owed.',
        '  8. The obligations - support expectations, regulations, customer data, promises.',
        '  9. The state of play - open issues, known bugs, planned work, technical debt.',
        '  10. The first 30 days - what a new maintainer should do, in order.',
        '- Answer with ONE JSON object and nothing else: no commentary, no code fences.',
        '',
        'Fields (JSON key, type, required, limit, what goes in it)',
        implode("\n", $fieldLines),
        '',
        'Allowed values',
        implode("\n", $optionLines),
        '',
        'Categories (use the slug, not the name)',
        implode("\n", $categoryLines),
        '',
        'Readiness levels',
        '  0 Listed, 1 Documented, 2 Agent-ready, 3 Handover-tested, 4 Transferred.',
        '',
        'Rules for the values',
        '- Use null for an optional field the owner cannot answer, and "" for an empty text field.',
        '- monthly_cost and effort_hours_per_month are numbers or null, never strings with units.',
        '- keywords, skills, assets and obligations are arrays of strings, even when empty.',
        '- assets must use the asset keys listed above.',
        '- Set synopsis_ai_drafted and brief_ai_drafted to true when you drafted those parts.',
        '',
        'Return exactly this shape:',
        json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ];

    return implode("\n", $lines);
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

    $limits = submission_field_limits();

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
 * Approved listings that expire inside the window and have not been reminded
 * about this expiry yet.
 *
 * The reminder is tied to the current `expires_at`, so a reconfirmation (which
 * moves it) makes the listing eligible again for the next cycle, while a cron
 * run repeated in the same cycle stays silent.
 *
 * @return array<int, array<string, mixed>>
 */
function projects_due_expiry_reminder(PDO $pdo, int $days = EXPIRY_REMINDER_DAYS): array
{
    $days = max(1, $days);

    $stmt = $pdo->prepare(
        "SELECT p.id, p.slug, p.title, p.expires_at,
                u.email AS owner_email, u.display_name AS owner_name
         FROM projects p
         JOIN users u ON u.id = p.owner_id
         WHERE p.review_status = 'approved'
           AND p.expires_at IS NOT NULL
           AND p.expires_at > UTC_TIMESTAMP()
           AND p.expires_at <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$days} DAY)
           AND NOT EXISTS (
               SELECT 1 FROM project_expiry_reminders r
               WHERE r.project_id = p.id AND r.expires_at = p.expires_at
           )
         ORDER BY p.expires_at ASC, p.id ASC"
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Record that the reminder for this expiry has gone out.
 */
function mark_expiry_reminder_sent(PDO $pdo, int $projectId, string $expiresAt): void
{
    $pdo->prepare(
        'INSERT IGNORE INTO project_expiry_reminders (project_id, expires_at, sent_at)
         VALUES (?, ?, UTC_TIMESTAMP())'
    )->execute([$projectId, $expiresAt]);
}

function reconfirm_url(string $rawToken): string
{
    return site_url() . '/reconfirm/?token=' . urlencode($rawToken);
}

/**
 * Push a published listing's expiry out six months and mark it confirmed.
 */
function reconfirm_project(PDO $pdo, int $projectId, ?int $actorUserId = null): void
{
    $stmt = $pdo->prepare(
        "UPDATE projects
         SET expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 6 MONTH),
             confirmed_at = UTC_TIMESTAMP()
         WHERE id = ? AND review_status = 'approved'"
    );
    $stmt->execute([$projectId]);

    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('That listing is not published, so it cannot be reconfirmed.');
    }

    $pdo->prepare(
        "INSERT INTO audit_log (actor_user_id, action, entity_type, entity_id, detail, created_at)
         VALUES (?, 'project.reconfirmed', 'project', ?, ?, UTC_TIMESTAMP())"
    )->execute([$actorUserId, $projectId, 'Reconfirmed from the expiry reminder']);
}

/**
 * Why a listing may not be deleted, in the words the owner will read.
 *
 * Deleting cascades to the listing's relations, revisions and resubmissions.
 * Interest rows cascade too, which is exactly why they are a blocker: someone
 * else's expression of interest is not the owner's to erase while they are
 * acting on it.
 *
 * @return array<int, string>
 */
function project_delete_blockers(PDO $pdo, int $projectId): array
{
    $blockers = [];

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM interests WHERE project_id = ?');
    $stmt->execute([$projectId]);
    $interests = (int) $stmt->fetchColumn();

    if ($interests === 1) {
        $blockers[] = 'One person has expressed interest in this listing.';
    } elseif ($interests > 1) {
        $blockers[] = $interests . ' people have expressed interest in this listing.';
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM project_revisions WHERE project_id = ? AND review_status = 'pending'"
    );
    $stmt->execute([$projectId]);

    if ((int) $stmt->fetchColumn() > 0) {
        $blockers[] = 'A revision is waiting for review.';
    }

    return $blockers;
}

/**
 * Delete a listing and everything hanging off it, leaving the audit trail.
 */
function delete_project(PDO $pdo, int $projectId, ?int $actorUserId = null): void
{
    $stmt = $pdo->prepare('SELECT slug, title FROM projects WHERE id = ?');
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();

    if ($project === false) {
        throw new RuntimeException('That listing no longer exists.');
    }

    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            "INSERT INTO audit_log (actor_user_id, action, entity_type, entity_id, detail, created_at)
             VALUES (?, 'project.deleted', 'project', ?, ?, UTC_TIMESTAMP())"
        )->execute([
            $actorUserId,
            $projectId,
            'Deleted "' . $project['title'] . '" (/' . $project['slug'] . '/)',
        ]);

        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$projectId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
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
