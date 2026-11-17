<?php

/**
 * Import a JSON listing drafted with an AI, and prefill the submission form.
 *
 * The JSON may arrive pasted into the form or as an uploaded file. Nothing is
 * created here: the text becomes form values, the same validator runs over
 * them, and the owner still reviews every field and presses submit. That keeps
 * the human check the rest of the site depends on.
 */

declare(strict_types=1);

require dirname(__DIR__, 3) . '/bootstrap.php';
require dirname(__DIR__, 3) . '/src/html.php';
require dirname(__DIR__, 3) . '/src/auth.php';
require dirname(__DIR__, 3) . '/src/projects.php';

$user = require_login();
$pdo = db();
$categories = $pdo->query('SELECT slug, name FROM categories ORDER BY sort_order, name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/submit/');
}

$errors = [];
$old = [];
$notice = '';
$maxBytes = 256 * 1024;

$pasted = is_string($_POST['json'] ?? null) ? trim((string) $_POST['json']) : '';

// A path typed or pasted into a file input never becomes an upload: the field
// arrives with no temporary file. Check that before reading, or an empty path
// throws a ValueError and the request dies with a 500.
$file = $_FILES['listing'] ?? null;
$tmpName = is_array($file) ? (string) ($file['tmp_name'] ?? '') : '';
$hasFile = is_array($file)
    && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
    && $tmpName !== ''
    && is_file($tmpName);

if (!verify_csrf((string) ($_POST['csrf'] ?? ''))) {
    $errors['form'] = 'The form expired. Try the import again.';
} elseif ($pasted === '' && !$hasFile) {
    $errors['form'] = 'Nothing to import. Paste the JSON into the box, or use Choose file — pasting a file path will not attach it.';
} elseif ($pasted !== '' && strlen($pasted) > $maxBytes) {
    $errors['form'] = 'That JSON is larger than 256 KB, which is too big for a listing.';
} elseif ($hasFile && (int) ($file['size'] ?? 0) > $maxBytes) {
    $errors['form'] = 'That file is larger than 256 KB, which is too big for a listing.';
} else {
    try {
        $text = $pasted !== '' ? $pasted : (string) file_get_contents($tmpName);
        $json = listing_from_json_text($text);

        $old = project_json_to_form($json);

        [$data, $errors] = validate_project_submission($old, $pdo);

        // The confirmation is the owner's own statement; it cannot be imported.
        unset($errors['confirm']);

        $source = $hasFile && $pasted === ''
            ? basename((string) ($file['name'] ?? 'file.json'))
            : 'the pasted JSON';

        $notice = 'Imported from ' . $source . '. Check every field, tick the confirmation, then submit.';
    } catch (InvalidArgumentException $e) {
        $errors['form'] = $e->getMessage()
            . ' Ask your AI for one JSON object, with the fields named in the prompt.';
    } catch (Throwable $e) {
        error_log('Relay: import failed: ' . $e->getMessage());
        app_log('import: ' . get_class($e) . ': ' . $e->getMessage());

        $errors['form'] = 'That could not be read as a listing. Try pasting the JSON instead of uploading it.';
    }
}

$content = render_template('submit', [
    'user' => $user,
    'categories' => $categories,
    'errors' => $errors,
    'old' => $old,
    'csrf' => csrf_token(),
    'statuses' => project_status_options(),
    'arrangements' => arrangement_options(),
    'revenueStates' => revenue_state_options(),
    'userScopes' => user_scope_options(),
    'readinessLevels' => readiness_options(),
    'assets' => asset_options(),
    'contactModes' => contact_mode_options(),
    'notice' => $notice,
    'prompt' => submission_prompt(array_column($categories, 'name', 'slug')),
    'importAction' => '/submit/import/',
]);

echo render_site_page(
    'Submit a project — Relay',
    'Describe a project that needs a next maintainer.',
    '/submit/import/',
    $content,
    true
);
