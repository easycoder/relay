<?php

/** @var array<string, mixed> $user */
/** @var array<int, array<string, mixed>> $interests */
/** @var string $csrf */
/** @var string $done */
/** @var string $error */
/** @var string $filterSlug */

?>
<section class="hero auth-hero">
    <p class="eyebrow">Expressions of interest</p>
    <h1>People who want to take something on</h1>
    <p class="lede">Contact details are hidden until you accept. Accepting sends an introduction to both of you.</p>
</section>

<?php if ($done === 'accept'): ?>
    <p class="notice">Accepted. The applicant's email is shown below; an introduction email has been sent if mail is working.</p>
<?php elseif ($done === 'decline'): ?>
    <p class="notice">Declined. The applicant will see the decision in their dashboard.</p>
<?php endif; ?>

<?php if ($error === 'interest'): ?>
    <p class="form-errors" role="alert">That expression had already been decided or no longer exists.</p>
<?php elseif ($error === 'form'): ?>
    <p class="form-errors" role="alert">The form expired. Please try again.</p>
<?php endif; ?>

<?php if ($interests === []): ?>
    <p class="section">No expressions of interest yet.</p>
<?php else: ?>
    <?php foreach ($interests as $interest): ?>
        <article class="card review-card">
            <p class="badges">
                <span class="badge"><?= e(interest_status_label($interest['status'])) ?></span>
                <span class="badge badge-quiet"><?= e($interest['project_title']) ?></span>
            </p>
            <h2><?= e($interest['applicant_name']) ?></h2>
            <p class="meta">
                Submitted <?= e(format_date($interest['created_at'])) ?>
                <?php if (!empty($interest['decided_at'])): ?>
                    · Decided <?= e(format_date($interest['decided_at'])) ?>
                <?php endif; ?>
            </p>

            <?php if ($interest['status'] === 'accepted'): ?>
                <p class="notice">
                    You accepted. You can reach <?= e($interest['applicant_name']) ?> at
                    <a href="mailto:<?= e($interest['applicant_email']) ?>"><?= e($interest['applicant_email']) ?></a>.
                </p>
            <?php elseif ($interest['status'] === 'declined' && !empty($interest['decision_note'])): ?>
                <p class="meta">Your reason: <?= e($interest['decision_note']) ?></p>
            <?php endif; ?>

            <details>
                <summary>Read the expression</summary>
                <h3>Experience</h3>
                <p><?= nl2br(e($interest['experience'] ?? '')) ?></p>
                <h3>Time available</h3>
                <p><?= e($interest['availability'] ?? '') ?></p>
                <h3>First 30 days</h3>
                <p><?= nl2br(e($interest['first_30_days'] ?? '')) ?></p>
                <h3>What they want</h3>
                <p><?= nl2br(e($interest['wants'] ?? '')) ?></p>
                <?php if (!empty($interest['message'])): ?>
                    <h3>Anything else</h3>
                    <p><?= nl2br(e($interest['message'])) ?></p>
                <?php endif; ?>
            </details>

            <?php if ($interest['status'] === 'new'): ?>
                <div class="review-actions">
                    <form method="post" action="/interests/">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="interest_id" value="<?= (int) $interest['id'] ?>">
                        <input type="hidden" name="action" value="accept">
                        <button class="button" type="submit">Accept</button>
                    </form>

                    <form method="post" action="/interests/" class="reject-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="interest_id" value="<?= (int) $interest['id'] ?>">
                        <input type="hidden" name="action" value="decline">
                        <label class="field"><span>Reason (optional, shown to the applicant)</span><input type="text" name="note" maxlength="500"></label>
                        <button class="button button-secondary" type="submit">Decline</button>
                    </form>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
