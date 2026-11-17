<?php

/** @var array<string, mixed> $user */
/** @var array<int, array<string, mixed>> $projects */
/** @var bool $submitted */
/** @var bool $updated */
/** @var bool $revision */
/** @var bool $switched */
/** @var bool $deleted */
/** @var bool $signoutStale */
/** @var string $csrf */
/** @var bool $isReviewer */
/** @var int|null $newInterestCount */
/** @var array<int, array<string, mixed>>|null $myInterests */
/** @var int $accountCount */

$newInterestCount = $newInterestCount ?? 0;
$myInterests = $myInterests ?? [];
$accountCount = $accountCount ?? 0;

?>
<section class="hero auth-hero">
    <p class="eyebrow">Dashboard</p>
    <h1>Your projects</h1>
    <p class="lede">Signed in as <?= e($user['display_name']) ?> (<?= e($user['email']) ?>).</p>
</section>

<?php if ($submitted): ?>
    <p class="notice">Your listing has been submitted. It will appear in the public catalogue after a person reviews it.</p>
<?php endif; ?>

<?php if (!empty($updated)): ?>
    <p class="notice">Your listing has been updated and returned to the review queue.</p>
<?php endif; ?>

<?php if (!empty($revision)): ?>
    <p class="notice">Your revision has been saved for review. The live listing stays visible until it is approved.</p>
<?php endif; ?>

<?php if (!empty($switched)): ?>
    <p class="notice">You are now signed in as <?= e($user['display_name']) ?> (<?= e($user['email']) ?>).</p>
<?php endif; ?>

<?php if (!empty($deleted)): ?>
    <p class="notice">The listing has been deleted. Run a build to remove its public page from the catalogue.</p>
<?php endif; ?>

<?php if (!empty($signoutStale)): ?>
    <p class="notice">The page you signed out from was out of date. This is the refreshed dashboard; press Sign out again.</p>
<?php endif; ?>

<div class="actions">
    <a class="button" href="/submit/">Submit a project</a>
    <?php if ($projects !== []): ?>
        <a class="button button-secondary" href="/interests/">Expressions of interest<?php if ((int) $newInterestCount > 0): ?> (<?= (int) $newInterestCount ?>)<?php endif; ?></a>
    <?php endif; ?>
    <?php if ($isReviewer): ?>
        <a class="button button-secondary" href="/review/">Review queue</a>
    <?php endif; ?>
    <?php if ((int) $accountCount > 0): ?>
        <a class="button button-secondary" href="/accounts/">Switch account (<?= (int) $accountCount ?>)</a>
    <?php else: ?>
        <a class="button button-secondary" href="/accounts/">Accounts</a>
    <?php endif; ?>
    <form method="post" action="/logout/" class="inline-form">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button class="button button-secondary" type="submit">Sign out</button>
    </form>
</div>

<?php if ($projects === []): ?>
    <p class="section">You have not submitted a project yet.</p>
<?php else: ?>
    <div class="cards section">
        <?php foreach ($projects as $project): ?>
            <?php
            $projectUrl = $project['review_status'] === 'approved'
                ? '/projects/' . rawurlencode((string) $project['slug']) . '/'
                : '/preview/?slug=' . urlencode((string) $project['slug']);
            ?>
            <article class="card">
                <p class="badges">
                    <span class="badge"><?= e(status_label($project['status'])) ?></span>
                    <span class="badge badge-quiet"><?= e(review_label($project['review_status'])) ?></span>
                    <?php if ((int) ($project['pending_revisions'] ?? 0) > 0): ?>
                        <span class="badge badge-quiet">Revision awaiting review</span>
                    <?php endif; ?>
                    <?php if ((int) ($project['new_interests'] ?? 0) > 0): ?>
                        <a class="badge badge-quiet" href="/interests/?project=<?= e($project['slug']) ?>"><?= (int) $project['new_interests'] ?> new expression<?= (int) $project['new_interests'] === 1 ? '' : 's' ?></a>
                    <?php endif; ?>
                </p>
                <h3><a href="<?= e($projectUrl) ?>"><?= e($project['title']) ?></a></h3>
                <p class="card-meta">Submitted <?= e(format_date($project['created_at'])) ?> · <a href="/edit/?slug=<?= e($project['slug']) ?>"><?= $project['review_status'] === 'rejected' ? 'Edit and resubmit' : 'Edit' ?></a></p>
                <?php if ($project['review_status'] === 'approved' && !empty($project['expires_at'])): ?>
                    <?php $daysLeft = days_until((string) $project['expires_at']); ?>
                    <p class="meta">
                        <?php if ($daysLeft === null): ?>
                            Expires <?= e(format_date($project['expires_at'])) ?>.
                        <?php elseif ($daysLeft < 0): ?>
                            Expired on <?= e(format_date($project['expires_at'])) ?>. It is out of the catalogue; edit and resubmit to bring it back.
                        <?php elseif ($daysLeft <= 21): ?>
                            Expires in <?= (int) $daysLeft ?> days, on <?= e(format_date($project['expires_at'])) ?> — reconfirm from the reminder email to keep it published.
                        <?php else: ?>
                            Published until <?= e(format_date($project['expires_at'])) ?>.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <?php if ($project['review_status'] === 'rejected'): ?>
                    <?php if (!empty($project['review_note'])): ?>
                        <p class="meta">Reviewer's reason: <?= e($project['review_note']) ?></p>
                    <?php endif; ?>
                    <p class="meta">Rejected. The details you entered are kept: edit what needs changing and saving sends it back for review.</p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($myInterests !== []): ?>
    <section class="section">
        <h2>Your expressions of interest</h2>
        <div class="cards">
            <?php foreach ($myInterests as $interest): ?>
                <article class="card">
                    <p class="badges">
                        <span class="badge"><?= e(interest_status_label($interest['status'])) ?></span>
                    </p>
                    <h3><a href="/projects/<?= e($interest['slug']) ?>/"><?= e($interest['title']) ?></a></h3>
                    <p class="card-meta">Owner: <?= e($interest['owner_name']) ?> · Sent <?= e(format_date($interest['created_at'])) ?></p>

                    <?php if ($interest['status'] === 'accepted'): ?>
                        <p class="notice">Accepted. You can reach the owner at <a href="mailto:<?= e($interest['owner_email']) ?>"><?= e($interest['owner_email']) ?></a>.</p>
                    <?php elseif ($interest['status'] === 'declined' && !empty($interest['decision_note'])): ?>
                        <p class="meta">Reason: <?= e($interest['decision_note']) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
