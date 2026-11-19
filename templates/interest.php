<?php

/** @var array<string, mixed>|false $project */
/** @var array<string, mixed> $user */
/** @var array<string, mixed>|null $existing */
/** @var bool $notOpen */
/** @var bool $ownListing */
/** @var bool $sent */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string $csrf */

$existing = $existing ?? null;

?>
<section class="hero auth-hero">
    <p class="eyebrow">Express interest</p>

    <?php if ($notOpen): ?>
        <h1>This listing is not open</h1>
        <p class="lede">It may be pending approval, expired, or no longer published.</p>
        <p class="actions"><a class="button" href="/projects/">Browse projects</a></p>
    <?php elseif ($ownListing): ?>
        <h1>This is your listing</h1>
        <p class="lede">You cannot express interest in your own project.</p>
        <p class="actions"><a class="button" href="/dashboard/">Back to the dashboard</a></p>
    <?php else: ?>
        <h1><?= e($project['title']) ?></h1>
        <p class="lede">Owner: <?= e($project['owner_name']) ?>. Your email is shared with the owner only if they accept.</p>
    <?php endif; ?>
</section>

<?php if (!$notOpen && !$ownListing): ?>
    <?php if ($existing !== null): ?>
        <?php if ($sent): ?>
            <p class="notice">Your expression of interest has been sent. The owner will see it; you will be emailed if they accept.</p>
        <?php endif; ?>

        <p class="notice"><?= e(interest_status_label($existing['status'])) ?></p>

        <?php if ($existing['status'] === 'accepted'): ?>
            <p>You can reach the owner at <a href="mailto:<?= e($project['owner_email']) ?>"><?= e($project['owner_email']) ?></a>.</p>
        <?php elseif ($existing['status'] === 'declined' && !empty($existing['decision_note'])): ?>
            <p>The owner's reason: <?= e($existing['decision_note']) ?></p>
        <?php endif; ?>

        <p class="actions">
            <a class="button button-secondary" href="/projects/<?= e($project['slug']) ?>/">Back to the listing</a>
        </p>
    <?php else: ?>
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

        <form class="form form-wide" method="post" action="/interest/?project=<?= e(urlencode($project['slug'])) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

            <div class="honeypot" hidden aria-hidden="true">
                <label>Leave this field empty <input type="text" name="relay_hp" tabindex="-1" autocomplete="off"></label>
            </div>

            <fieldset>
                <legend>What you would bring</legend>

                <label class="field">
                    <span>Relevant experience</span>
                    <textarea name="experience" rows="5" required><?= e(form_value($old, 'experience')) ?></textarea>
                </label>

                <label class="field">
                    <span>Time available</span>
                    <input type="text" name="availability" value="<?= e(form_value($old, 'availability')) ?>" maxlength="120" required placeholder="A few hours a week, or more">
                </label>

                <label class="field">
                    <span>What you would do in the first 30 days</span>
                    <textarea name="first_30_days" rows="6" required><?= e(form_value($old, 'first_30_days')) ?></textarea>
                </label>

                <label class="field">
                    <span>What you want from the project</span>
                    <textarea name="wants" rows="4" required><?= e(form_value($old, 'wants')) ?></textarea>
                </label>

                <label class="field">
                    <span>Anything else (optional)</span>
                    <textarea name="message" rows="3"><?= e(form_value($old, 'message')) ?></textarea>
                </label>

                <label class="check">
                    <input type="checkbox" name="confirm" value="1" required<?= form_checked($old, 'confirm', '1') ?>>
                    I understand that my name and email may be shared with the owner if they accept.
                </label>

                <p class="meta">The owner sees your answers but not your contact details until they accept.</p>

                <div class="actions">
                    <button class="button" type="submit">Send expression of interest</button>
                    <a class="button button-secondary" href="/projects/<?= e($project['slug']) ?>/">Cancel</a>
                </div>
            </fieldset>
        </form>
    <?php endif; ?>
<?php endif; ?>
