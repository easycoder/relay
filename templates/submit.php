<?php

/** @var array<string, mixed> $user */
/** @var array<int, array<string, string>> $categories */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string $csrf */
/** @var array<string, string> $statuses */
/** @var array<string, string> $arrangements */
/** @var array<string, string> $revenueStates */
/** @var array<string, string> $userScopes */
/** @var array<int, string> $readinessLevels */
/** @var array<string, string> $assets */
/** @var array<string, string> $contactModes */
/** @var string $eyebrow */
/** @var string $heading */
/** @var string $intro */
/** @var string $formAction */
/** @var string $submitLabel */
/** @var string $notice */
/** @var string $cancelUrl */
/** @var string $reviewNote */
/** @var int $resubmissionsUsed */
/** @var int $resubmissionLimit */
/** @var string|null $resubmitOpenAt */

$eyebrow = $eyebrow ?? 'Submit';
$heading = $heading ?? 'Describe a project';
$intro = $intro ?? "The listing is published after a person reviews it. The brief is the part an adopter's agent will read first; make it honest.";
$formAction = $formAction ?? '/submit/';
$submitLabel = $submitLabel ?? 'Submit for review';
$notice = $notice ?? '';
$cancelUrl = $cancelUrl ?? '';
$reviewNote = $reviewNote ?? '';
$resubmissionsUsed = $resubmissionsUsed ?? 0;
$resubmissionLimit = $resubmissionLimit ?? 0;
$resubmitOpenAt = $resubmitOpenAt ?? null;
$submissionPauseUntil = $submissionPauseUntil ?? null;
$submissionStrikes = $submissionStrikes ?? 0;
$pendingLimit = $pendingLimit ?? 0;
$pendingCount = $pendingCount ?? 0;
$listingsLimit = $listingsLimit ?? 0;
$listingsUsed = $listingsUsed ?? 0;
$listingsWindowDays = $listingsWindowDays ?? 30;

$saveDisabled = $resubmitOpenAt !== null || $submissionPauseUntil !== null;

?>
<section class="hero auth-hero">
    <p class="eyebrow"><?= e($eyebrow) ?></p>
    <h1><?= e($heading) ?></h1>
    <p class="lede"><?= e($intro) ?></p>
</section>

<?php if ($errors !== []): ?>
    <div class="form-errors" role="alert">
        <p><strong>Please fix the following:</strong></p>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($notice !== ''): ?>
    <p class="notice"><?= e($notice) ?></p>
<?php endif; ?>

<?php if ($reviewNote !== ''): ?>
    <div class="notice" role="status">
        <p><strong>The reviewer's reason:</strong> <?= e($reviewNote) ?></p>
        <p class="meta">Saving does not keep this reason: the listing goes back for review as a new submission.</p>
    </div>
<?php endif; ?>

<?php if ($submissionPauseUntil !== null): ?>
    <p class="notice">Submissions are paused for this account until <?= e(format_date($submissionPauseUntil)) ?>: <?= (int) $submissionStrikes ?> listings were rejected in the last <?= (int) $listingsWindowDays ?> days. Rejections age out of the window one at a time, so this clears by itself.</p>
<?php endif; ?>

<?php if ((int) $listingsLimit > 0 && $submissionPauseUntil === null): ?>
    <p class="meta">New listings: <?= (int) $listingsUsed ?> of <?= (int) $listingsLimit ?> in the last <?= (int) $listingsWindowDays ?> days. Up to <?= (int) $pendingLimit ?> may be waiting for review at once<?php if ((int) $pendingLimit > 0): ?> (<?= (int) $pendingCount ?> now)<?php endif; ?>.</p>
<?php endif; ?>

<?php if ((int) $resubmissionLimit > 0): ?>
    <?php if ($resubmitOpenAt !== null): ?>
        <p class="notice">Resubmission is closed for now. This listing has used all <?= (int) $resubmissionLimit ?> of its resubmissions in the last thirty days; it opens again on <?= e(format_date($resubmitOpenAt)) ?>.</p>
    <?php else: ?>
        <p class="meta">Resubmissions: <?= (int) $resubmissionsUsed ?> of <?= (int) $resubmissionLimit ?> used in the last thirty days.</p>
    <?php endif; ?>
<?php endif; ?>

<form class="form form-wide" method="post" action="<?= e($formAction) ?>">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

    <div class="honeypot" hidden aria-hidden="true">
        <label>Leave this field empty <input type="text" name="relay_hp" tabindex="-1" autocomplete="off"></label>
    </div>

    <fieldset>
        <legend>The project</legend>

        <label class="field">
            <span>Title</span>
            <input type="text" name="title" value="<?= e(form_value($old, 'title')) ?>" maxlength="200" required>
        </label>

        <label class="field">
            <span>One-line pitch</span>
            <input type="text" name="pitch" value="<?= e(form_value($old, 'pitch')) ?>" maxlength="300" required>
            <small>The only thing most visitors read. Keep it specific.</small>
        </label>

        <label class="field">
            <span>Synopsis</span>
            <textarea name="synopsis" rows="8" required><?= e(form_value($old, 'synopsis')) ?></textarea>
            <small>150–250 words. What it is, who uses it, and why it matters.</small>
        </label>

        <label class="field">
            <span>Adoption Brief (Markdown)</span>
            <textarea name="brief" rows="16" required><?= e(form_value($old, 'brief')) ?></textarea>
            <small>Use ## headings and - lists. Cover what a successor inherits, the state of play, the first 30 days, what is not for transfer and how to verify it.</small>
        </label>
    </fieldset>

    <fieldset>
        <legend>Status and fit</legend>

        <div class="field-row">
            <label class="field">
                <span>Status</span>
                <select name="status" required>
                    <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= form_selected($old, 'status', $value, 'seeking-co-maintainer') ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Arrangement</span>
                <select name="arrangement" required>
                    <option value="">Choose…</option>
                    <?php foreach ($arrangements as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= form_selected($old, 'arrangement', $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Category</span>
                <select name="category" required>
                    <option value="">Choose…</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['slug']) ?>"<?= form_selected($old, 'category', $category['slug']) ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="field-row">
            <label class="field">
                <span>Revenue</span>
                <select name="revenue_state" required>
                    <option value="">Choose…</option>
                    <?php foreach ($revenueStates as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= form_selected($old, 'revenue_state', $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Revenue model (optional)</span>
                <input type="text" name="revenue_model" value="<?= e(form_value($old, 'revenue_model')) ?>" maxlength="120">
            </label>

            <label class="field">
                <span>Users</span>
                <select name="user_scope" required>
                    <option value="">Choose…</option>
                    <?php foreach ($userScopes as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= form_selected($old, 'user_scope', $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="field-row">
            <label class="field">
                <span>Monthly cost (optional)</span>
                <input type="number" name="monthly_cost" value="<?= e(form_value($old, 'monthly_cost')) ?>" min="0" step="0.01">
            </label>

            <label class="field">
                <span>Currency</span>
                <input type="text" name="currency" value="<?= e(form_value($old, 'currency')) ?>" maxlength="3" placeholder="GBP">
            </label>

            <label class="field">
                <span>Effort hours / month</span>
                <input type="number" name="effort_hours_per_month" value="<?= e(form_value($old, 'effort_hours_per_month')) ?>" min="0" max="1000" step="1">
            </label>
        </div>

        <label class="field">
            <span>What the running costs pay for</span>
            <input type="text" name="cost_description" value="<?= e(form_value($old, 'cost_description')) ?>" maxlength="500">
        </label>

        <label class="field">
            <span>What consumes the effort</span>
            <input type="text" name="effort_description" value="<?= e(form_value($old, 'effort_description')) ?>" maxlength="500">
        </label>

        <div class="field-row">
            <label class="field">
                <span>Licence</span>
                <input type="text" name="licence" value="<?= e(form_value($old, 'licence')) ?>" maxlength="64" placeholder="Apache-2.0">
            </label>

            <label class="field">
                <span>Readiness</span>
                <select name="readiness" required>
                    <?php foreach ($readinessLevels as $value => $label): ?>
                        <option value="<?= $value ?>"<?= form_selected($old, 'readiness', (string) $value, '0') ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Jurisdiction</span>
                <input type="text" name="jurisdiction" value="<?= e(form_value($old, 'jurisdiction', 'GB')) ?>" maxlength="64">
            </label>

            <label class="field">
                <span>Language</span>
                <input type="text" name="language" value="<?= e(form_value($old, 'language', 'en')) ?>" maxlength="16">
            </label>

            <label class="field">
                <span>Timezone</span>
                <input type="text" name="timezone" value="<?= e(form_value($old, 'timezone', 'Europe/London')) ?>" maxlength="64">
            </label>
        </div>
    </fieldset>

    <fieldset>
        <legend>Links and tags</legend>

        <div class="field-row">
            <label class="field"><span>Repository URL</span><input type="url" name="repo_url" value="<?= e(form_value($old, 'repo_url')) ?>" maxlength="255"></label>
            <label class="field"><span>Live site URL</span><input type="url" name="site_url" value="<?= e(form_value($old, 'site_url')) ?>" maxlength="255"></label>
        </div>

        <div class="field-row">
            <label class="field"><span>Documentation URL</span><input type="url" name="docs_url" value="<?= e(form_value($old, 'docs_url')) ?>" maxlength="255"></label>
            <label class="field"><span>Demo URL</span><input type="url" name="demo_url" value="<?= e(form_value($old, 'demo_url')) ?>" maxlength="255"></label>
        </div>

        <label class="field">
            <span>Keywords</span>
            <input type="text" name="keywords" value="<?= e(form_value($old, 'keywords')) ?>" placeholder="heating, mqtt, esp32">
            <small>Comma or newline separated.</small>
        </label>

        <label class="field">
            <span>Skills</span>
            <input type="text" name="skills" value="<?= e(form_value($old, 'skills')) ?>" placeholder="php, mysql, python">
        </label>

        <fieldset class="checks">
            <legend>What a successor inherits</legend>
            <?php foreach ($assets as $value => $label): ?>
                <label class="check"><input type="checkbox" name="assets[]" value="<?= e($value) ?>"<?= form_checked($old, 'assets', $value) ?>> <?= e($label) ?></label>
            <?php endforeach; ?>
        </fieldset>

        <label class="field">
            <span>Obligations</span>
            <textarea name="obligations" rows="4"><?= e(form_value($old, 'obligations')) ?></textarea>
            <small>One per line: support, regulatory, data protection, promises to customers.</small>
        </label>
    </fieldset>

    <fieldset>
        <legend>Contact and confirmation</legend>

        <label class="field">
            <span>Contact mode</span>
            <select name="contact_mode" required>
                <?php foreach ($contactModes as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= form_selected($old, 'contact_mode', $value, 'via-site') ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Public contact route (optional)</span>
            <input type="text" name="public_contact" value="<?= e(form_value($old, 'public_contact')) ?>" maxlength="255" placeholder="email or https://…">
        </label>

        <label class="check"><input type="checkbox" name="synopsis_ai_drafted" value="1"<?= form_checked($old, 'synopsis_ai_drafted', '1') ?>> The synopsis was drafted with AI; I have checked it.</label>
        <label class="check"><input type="checkbox" name="brief_ai_drafted" value="1"<?= form_checked($old, 'brief_ai_drafted', '1') ?>> The brief was drafted with AI; I have checked it.</label>

        <label class="check"><input type="checkbox" name="confirm" value="1" required<?= form_checked($old, 'confirm', '1') ?>> I confirm that this description is accurate and that I am the owner or an authorised representative.</label>

        <p class="meta">Submissions are reviewed by a person before publication. Nothing is published automatically.</p>
        <div class="actions">
            <button class="button" type="submit"<?= $saveDisabled ? ' disabled' : '' ?>><?= e($submitLabel) ?></button>
            <?php if ($cancelUrl !== ''): ?>
                <a class="button button-secondary" href="<?= e($cancelUrl) ?>">Cancel</a>
            <?php endif; ?>
        </div>
    </fieldset>
</form>
