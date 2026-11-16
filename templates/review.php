<?php

/** @var array<string, mixed> $user */
/** @var array<int, array<string, mixed>> $projects */
/** @var array<int, array<string, mixed>> $revisions */
/** @var string $csrf */
/** @var string $done */
/** @var string $error */

$revisions = $revisions ?? [];
$error = $error ?? '';

?>
<section class="hero auth-hero">
    <p class="eyebrow">Review</p>
    <h1>Pending listings</h1>
    <p class="lede">Only approved listings are published. Review the synopsis and brief before deciding.</p>
</section>

<?php if ($done === 'approve'): ?>
    <p class="notice">Listing approved. Run <code>php scripts/build.php</code> to publish it.</p>
<?php elseif ($done === 'reject'): ?>
    <p class="notice">Listing rejected. It will not be published.</p>
<?php elseif ($done === 'revision-approve'): ?>
    <p class="notice">Revision approved. The live listing has the new details; run <code>php scripts/build.php</code> to update the static pages.</p>
<?php elseif ($done === 'revision-reject'): ?>
    <p class="notice">Revision rejected. The live listing is unchanged.</p>
<?php endif; ?>

<?php if ($error === 'revision'): ?>
    <p class="form-errors" role="alert">That revision had already been reviewed or no longer exists.</p>
<?php endif; ?>

<?php if ($projects === [] && $revisions === []): ?>
    <p class="section">Nothing is awaiting review.</p>
<?php endif; ?>

<?php if ($projects !== []): ?>
    <section class="section">
        <h2>New listings</h2>

        <?php foreach ($projects as $project): ?>
            <article class="card review-card">
                <p class="badges">
                    <span class="badge"><?= e(status_label($project['status'])) ?></span>
                    <span class="badge badge-quiet"><?= e($project['category_name']) ?></span>
                </p>
                <h2><?= e($project['title']) ?></h2>
                <p class="pitch"><?= e($project['pitch']) ?></p>
                <p class="meta">Owner: <?= e($project['owner_name']) ?> · Submitted <?= e(format_date($project['created_at'])) ?></p>

                <details>
                    <summary>Read the synopsis and brief</summary>
                    <h3>Synopsis</h3>
                    <p><?= nl2br(e($project['synopsis'])) ?></p>
                    <h3>Adoption Brief</h3>
                    <?= render_markdown($project['brief'], 2) ?>
                </details>

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
                        <label class="field"><span>Reason (optional)</span><input type="text" name="note" maxlength="500"></label>
                        <button class="button button-secondary" type="submit">Reject</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($revisions !== []): ?>
    <section class="section">
        <h2>Proposed revisions</h2>
        <p class="lede">The live listing stays published while these changes wait for a decision.</p>

        <?php foreach ($revisions as $revision): ?>
            <?php $data = $revision['data'] ?? []; ?>
            <article class="card review-card">
                <p class="badges">
                    <span class="badge badge-quiet">Revision</span>
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

                <details>
                    <summary>Read the proposed revision</summary>
                    <h3>Synopsis</h3>
                    <p><?= nl2br(e($data['synopsis'] ?? '')) ?></p>
                    <h3>Adoption Brief</h3>
                    <?= render_markdown($data['brief'] ?? '', 2) ?>
                    <h3>Other fields</h3>
                    <ul>
                        <li>Status: <?= e(status_label($data['status'] ?? '')) ?></li>
                        <li>Arrangement: <?= e(arrangement_label($data['arrangement'] ?? '')) ?></li>
                        <li>Category: <?= e($data['category'] ?? '') ?></li>
                        <li>Readiness: <?= e(readiness_label((int) ($data['readiness'] ?? 0))) ?></li>
                        <li>Licence: <?= e($data['licence'] ?? '') ?></li>
                    </ul>
                </details>

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
