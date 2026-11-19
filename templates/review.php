<?php

/** @var array<string, mixed> $user */
/** @var array<int, array<string, mixed>> $projects */
/** @var array<int, array<string, mixed>> $recentEdits */
/** @var int $editReviewDays */
/** @var array<int, array<string, mixed>> $recentApprovals */
/** @var array<int, array<int, array<string, mixed>>> $endorsements */
/** @var int $likeThreshold */
/** @var array<int, array<string, mixed>> $revisions */
/** @var string $csrf */
/** @var string $done */
/** @var string $error */
/** @var int|null $published */
/** @var int $removed */

$recentEdits = $recentEdits ?? [];
$editReviewDays = (int) ($editReviewDays ?? 14);
$recentApprovals = $recentApprovals ?? [];
$endorsements = $endorsements ?? [];
$likeThreshold = (int) ($likeThreshold ?? 5);
$revisions = $revisions ?? [];
$error = $error ?? '';
$published = $published ?? null;
$removed = $removed ?? 0;

?>
<section class="hero auth-hero">
    <p class="eyebrow">Review</p>
    <h1>Review queue</h1>
    <p class="lede">New listings need review before publication. Edits to live listings go live immediately and are re-reviewed afterwards. A rejection blocks automatic approval; a veto reverses a community approval.</p>
</section>

<div class="actions section">
    <form method="post" action="/publish/">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button class="button" type="submit">Publish now</button>
    </form>
</div>
<p class="meta">Rebuilds the public catalogue from the database. Approval does not publish a listing on its own.</p>

<?php if ($done === 'approve'): ?>
    <p class="notice">Listing approved. Press <strong>Publish now</strong> above to put it in the catalogue.</p>
<?php elseif ($done === 'reject'): ?>
    <p class="notice">Listing rejected. It will not be published.</p>
<?php elseif ($done === 'veto'): ?>
    <p class="notice">Community approval vetoed. The listing is rejected, and a later round of likes cannot approve it again.</p>
<?php elseif ($done === 'edit-accepted'): ?>
    <p class="notice">Edit accepted. It leaves the re-review list; the static page drops the Edited marker on the next build.</p>
<?php elseif ($done === 'taken-down'): ?>
    <p class="notice">Listing taken down. The owner has been emailed the reason. Press <strong>Publish now</strong> above to remove the page from the catalogue.</p>
<?php elseif ($done === 'revision-approve'): ?>
    <p class="notice">Revision approved. The live listing has the new details; press <strong>Publish now</strong> to update the static pages.</p>
<?php elseif ($done === 'revision-reject'): ?>
    <p class="notice">Revision rejected. The live listing is unchanged.</p>
<?php endif; ?>

<?php if ($published !== null): ?>
    <p class="notice">Published: <?= (int) $published ?> listing<?= $published === 1 ? '' : 's' ?> rebuilt<?php if ($removed > 0): ?>, <?= (int) $removed ?> stale page<?= $removed === 1 ? '' : 's' ?> removed<?php endif; ?>.</p>
<?php endif; ?>

<?php if ($error === 'build'): ?>
    <p class="form-errors" role="alert">The build failed, so every page is unchanged. Check that <code>public/</code> is writable and read <code>storage/error.log</code>.</p>
<?php elseif ($error === 'busy'): ?>
    <p class="form-errors" role="alert">A build is already running. Try again in a moment.</p>
<?php elseif ($error === 'stale'): ?>
    <p class="form-errors" role="alert">That request was out of date. Press Publish now again.</p>
<?php elseif ($error === 'revision'): ?>
    <p class="form-errors" role="alert">That revision had already been reviewed or no longer exists.</p>
<?php elseif ($error === 'veto'): ?>
    <p class="form-errors" role="alert">That listing could not be vetoed. It may no longer be approved.</p>
<?php elseif ($error === 'veto-note'): ?>
    <p class="form-errors" role="alert">A veto needs a reason, because it overrides other people's likes.</p>
<?php elseif ($error === 'takedown'): ?>
    <p class="form-errors" role="alert">That listing could not be taken down. It may no longer be live.</p>
<?php elseif ($error === 'takedown-note'): ?>
    <p class="form-errors" role="alert">A take-down needs a reason, because it is shown to the owner and emailed to them.</p>
<?php elseif ($error === 'edit-accepted'): ?>
    <p class="form-errors" role="alert">That edit could not be accepted. It may already have left the re-review list.</p>
<?php endif; ?>

<?php if ($projects === [] && $revisions === [] && $recentApprovals === [] && $recentEdits === []): ?>
    <p class="section">Nothing is awaiting review.</p>
<?php endif; ?>

<?php if ($projects !== []): ?>
    <section class="section">
        <h2>New listings</h2>

        <?php foreach ($projects as $project): ?>
            <?php $reasons = $endorsements[(int) $project['id']] ?? []; ?>
            <article class="card review-card">
                <p class="badges">
                    <span class="badge"><?= e(stage_label((string) ($project['stage'] ?? 'live'))) ?></span>
                    <span class="badge"><?= e(status_label($project['status'])) ?></span>
                    <span class="badge badge-quiet"><?= e($project['category_name']) ?></span>
                    <span class="badge badge-quiet"><?= (int) $project['like_count'] ?> of <?= (int) $likeThreshold ?> likes</span>
                </p>
                <h2><?= e($project['title']) ?></h2>
                <p class="pitch"><?= e($project['pitch']) ?></p>
                <p class="meta">Owner: <?= e($project['owner_name']) ?> · Submitted <?= e(format_date($project['created_at'])) ?></p>

                <?php
                $fullHtml = '<h3>Synopsis</h3><p>' . nl2br(e($project['synopsis'])) . '</p>'
                    . '<h3>' . e(brief_label((string) ($project['stage'] ?? 'live'))) . '</h3>'
                    . render_markdown($project['brief'], 2);
                $reviewDiff = review_diff_from_json($project['review_diff'] ?? null);
                ?>
                <?php if ($reviewDiff !== null): ?>
                    <?= render_review_tabs('new-' . (int) $project['id'], $fullHtml, $reviewDiff) ?>
                <?php else: ?>
                    <details>
                        <summary>Read the synopsis and brief</summary>
                        <?= $fullHtml ?>
                    </details>
                <?php endif; ?>

                <?php if ($reasons !== []): ?>
                    <details>
                        <summary>Read the <?= count($reasons) ?> like<?= count($reasons) === 1 ? '' : 's' ?></summary>
                        <ul class="endorsement-list">
                            <?php foreach ($reasons as $endorsement): ?>
                                <li class="endorsement">
                                    <p class="meta"><?= e((string) $endorsement['display_name']) ?> · <?= e(format_date($endorsement['created_at'])) ?></p>
                                    <p><?= nl2br(e((string) $endorsement['reason'])) ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>

                <div class="review-actions">
                    <form method="post" action="/review/">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="kind" value="project">
                        <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button class="button" type="submit">Approve</button>
                    </form>

                    <form method="post" action="/review/" class="reject-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="kind" value="project">
                        <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <label class="field"><span>Reason (shown to the owner)</span><input type="text" name="note" maxlength="500"></label>
                        <button class="button button-secondary" type="submit">Reject</button>
                    </form>
                </div>
                <p class="meta">A rejection blocks automatic approval for this listing; the owner must edit and resubmit, and a person must approve it.</p>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($recentEdits !== []): ?>
    <section class="section">
        <h2>Recently edited</h2>
        <p class="lede">Edits to live listings go live immediately. If nobody acts, they leave this list as accepted after <?= (int) $editReviewDays ?> days, and the Edited marker disappears. Review the diff, accept it, or take the listing down with a reason.</p>

        <?php foreach ($recentEdits as $project): ?>
            <?php
            $fullHtml = '<h3>Synopsis</h3><p>' . nl2br(e($project['synopsis'])) . '</p>'
                . '<h3>' . e(brief_label((string) ($project['stage'] ?? 'live'))) . '</h3>'
                . render_markdown($project['brief'], 2);
            $reviewDiff = review_diff_from_json($project['review_diff'] ?? null);
            ?>
            <article class="card review-card">
                <p class="badges">
                    <span class="badge"><?= e(stage_label((string) ($project['stage'] ?? 'live'))) ?></span>
                    <span class="badge badge-quiet"><?= e(status_label($project['status'])) ?></span>
                    <span class="badge badge-quiet"><?= e($project['category_name']) ?></span>
                    <?php $likeLabel = like_count_label((int) ($project['like_count'] ?? 0)); ?>
                    <?php if ($likeLabel !== ''): ?><span class="badge badge-quiet"><?= e($likeLabel) ?></span><?php endif; ?>
                    <span class="badge">Edited <?= e(format_date($project['edited_at'])) ?></span>
                </p>
                <h2><?= e($project['title']) ?></h2>
                <p class="pitch"><?= e($project['pitch']) ?></p>
                <p class="meta">Owner: <?= e($project['owner_name']) ?> · Edited <?= e(format_date($project['edited_at'])) ?> · <a href="/preview/?slug=<?= e(urlencode((string) $project['slug'])) ?>">Open the listing</a></p>

                <?php if ($reviewDiff !== null): ?>
                    <?= render_review_tabs('edit-' . (int) $project['id'], $fullHtml, $reviewDiff) ?>
                <?php else: ?>
                    <details>
                        <summary>Read the listing</summary>
                        <?= $fullHtml ?>
                    </details>
                <?php endif; ?>

                <div class="review-actions">
                    <form method="post" action="/review/">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="kind" value="project">
                        <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                        <input type="hidden" name="action" value="accept-edit">
                        <button class="button" type="submit">Accept edit</button>
                    </form>

                    <form method="post" action="/review/" class="reject-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="kind" value="project">
                        <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                        <input type="hidden" name="action" value="takedown">
                        <label class="field"><span>Reason (shown and emailed to the owner)</span><input type="text" name="note" maxlength="500" required></label>
                        <button class="button button-secondary" type="submit">Take down</button>
                    </form>
                </div>
                <p class="meta">If neither action is taken, this leaves the list as accepted after <?= (int) $editReviewDays ?> days.</p>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($recentApprovals !== []): ?>
    <section class="section">
        <h2>Recently approved by the community</h2>
        <p class="lede">These reached the like threshold in the last thirty days. Read what people said, and veto anything that should not be in the catalogue.</p>

        <?php foreach ($recentApprovals as $project): ?>
            <?php $reasons = $endorsements[(int) $project['id']] ?? []; ?>
            <article class="card review-card">
                <p class="badges">
                    <span class="badge"><?= e(stage_label((string) ($project['stage'] ?? 'live'))) ?></span>
                    <span class="badge badge-quiet"><?= e(status_label($project['status'])) ?></span>
                    <span class="badge badge-quiet"><?= e($project['category_name']) ?></span>
                    <?php $likeLabel = like_count_label((int) ($project['like_count'] ?? count($reasons))); ?>
                    <?php if ($likeLabel !== ''): ?><span class="badge badge-quiet"><?= e($likeLabel) ?></span><?php endif; ?>
                </p>
                <h2><?= e($project['title']) ?></h2>
                <p class="pitch"><?= e($project['pitch']) ?></p>
                <p class="meta">Owner: <?= e($project['owner_name']) ?> · Approved <?= e(format_date($project['published_at'])) ?> · <a href="/preview/?slug=<?= e(urlencode((string) $project['slug'])) ?>">Open the listing</a></p>

                <details>
                    <summary>Read the likes</summary>
                    <ul class="endorsement-list">
                        <?php foreach ($reasons as $endorsement): ?>
                            <li class="endorsement">
                                <p class="meta"><?= e((string) $endorsement['display_name']) ?> · <?= e(format_date($endorsement['created_at'])) ?></p>
                                <p><?= nl2br(e((string) $endorsement['reason'])) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>

                <form method="post" action="/review/" class="reject-form">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="kind" value="project">
                    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                    <input type="hidden" name="action" value="veto">
                    <label class="field"><span>Reason for the veto (shown to the owner)</span><input type="text" name="note" maxlength="500" required></label>
                    <button class="button button-secondary" type="submit">Veto this approval</button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($revisions !== []): ?>
    <section class="section">
        <h2>Earlier revisions</h2>
        <p class="lede">These predate the immediate-edit policy; new edits go live and appear under Recently edited. Approving one applies it; rejecting it leaves the live listing unchanged.</p>

        <?php foreach ($revisions as $revision): ?>
            <?php $data = $revision['data'] ?? []; ?>
            <article class="card review-card">
                <p class="badges">
                    <span class="badge badge-quiet">Revision</span>
                    <span class="badge"><?= e(stage_label((string) ($data['stage'] ?? 'live'))) ?></span>
                    <?php if (!empty($data['status'])): ?>
                        <span class="badge"><?= e(status_label($data['status'])) ?></span>
                    <?php endif; ?>
                </p>
                <h2><?= e($data['title'] ?? $revision['project_title']) ?></h2>
                <p class="pitch"><?= e($data['pitch'] ?? '') ?></p>
                <p class="meta">
                    For <a href="/projects/<?= e($revision['slug']) ?>/"><?= e($revision['project_title']) ?></a>
                    · Owner: <?= e($revision['owner_name']) ?>
                    · Submitted <?= e(format_date($revision['created_at'])) ?>
                </p>

                <?php
                $fullHtml = '<h3>Synopsis</h3><p>' . nl2br(e($data['synopsis'] ?? '')) . '</p>'
                    . '<h3>' . e(brief_label((string) ($data['stage'] ?? 'live'))) . '</h3>'
                    . render_markdown($data['brief'] ?? '', 2)
                    . '<h3>Other fields</h3><ul>'
                    . '<li>Stage: ' . e(stage_label((string) ($data['stage'] ?? 'live'))) . '</li>'
                    . '<li>Status: ' . e(status_label($data['status'] ?? '')) . '</li>'
                    . '<li>Arrangement: ' . e(arrangement_label($data['arrangement'] ?? '')) . '</li>'
                    . '<li>Category: ' . e($data['category'] ?? '') . '</li>'
                    . '<li>Readiness: ' . e(readiness_label((int) ($data['readiness'] ?? 0))) . '</li>'
                    . '<li>Licence: ' . e($data['licence'] ?? '') . '</li>'
                    . '</ul>';
                $reviewDiff = review_diff_from_json($revision['diff'] ?? null);
                ?>
                <?php if ($reviewDiff !== null): ?>
                    <?= render_review_tabs('rev-' . (int) $revision['id'], $fullHtml, $reviewDiff) ?>
                <?php else: ?>
                    <details>
                        <summary>Read the proposed revision</summary>
                        <?= $fullHtml ?>
                    </details>
                <?php endif; ?>

                <div class="review-actions">
                    <form method="post" action="/review/">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="kind" value="revision">
                        <input type="hidden" name="revision_id" value="<?= (int) $revision['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button class="button" type="submit">Approve revision</button>
                    </form>

                    <form method="post" action="/review/" class="reject-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="kind" value="revision">
                        <input type="hidden" name="revision_id" value="<?= (int) $revision['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <label class="field"><span>Reason (optional)</span><input type="text" name="note" maxlength="500"></label>
                        <button class="button button-secondary" type="submit">Reject revision</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
