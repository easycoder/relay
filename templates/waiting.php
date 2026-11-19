<?php

/** @var array<string, mixed> $project */
/** @var int $count */
/** @var int $threshold */
/** @var array<int, array<string, mixed>> $endorsements */
/** @var array{allowed: bool, reason: string} $eligibility */
/** @var array<string, mixed>|null $user */
/** @var array<string, mixed>|null $myEndorsement */
/** @var string $likeMessage */
/** @var string $csrf */
/** @var string $reportUrl */

$remaining = max(0, (int) $threshold - (int) $count);

?>
<section class="notice">
    <p><strong>Pending approval.</strong> This listing has not been checked by Relay. It appears as written by the owner, and may be inaccurate or incomplete.</p>
    <p>
        <?= (int) $count ?> of <?= (int) $threshold ?> likes.
        <?php if ($remaining > 0): ?>
            <?= (int) $remaining ?> more will approve it automatically, unless a reviewer has asked to see it.
        <?php else: ?>
            The threshold has been reached; approval follows unless a reviewer has blocked it.
        <?php endif; ?>
    </p>
</section>

<?php if ($likeMessage !== ''): ?>
    <p class="notice" role="status"><?= e($likeMessage) ?></p>
<?php endif; ?>

<section class="section endorsement-panel">
    <h2>Like this listing</h2>

    <?php if ($user === null): ?>
        <p>Sign in to like a listing. A reason is required, and accounts must be a few days old.</p>
        <p><a class="button" href="/login/">Sign in</a></p>
    <?php elseif ($eligibility['allowed']): ?>
        <p>Liking says this listing belongs in Relay. Give a reason: it is shown here and to the reviewer.</p>

        <form method="post" action="/like/" class="form">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="slug" value="<?= e((string) $project['slug']) ?>">

            <label class="field">
                <span>Why does this belong here?</span>
                <textarea name="reason" rows="4" minlength="<?= like_reason_min_length() ?>" maxlength="<?= like_reason_max_length() ?>" required></textarea>
                <small>At least <?= like_reason_min_length() ?> characters. Be specific; “looks good” helps nobody.</small>
            </label>

            <div class="actions">
                <button class="button" type="submit">Like</button>
            </div>
        </form>
    <?php elseif ($eligibility['reason'] === 'already'): ?>
        <p>You liked this listing on <?= e(format_date($myEndorsement['created_at'] ?? null)) ?>.</p>
    <?php elseif ($eligibility['reason'] === 'own'): ?>
        <p>This is your listing; you cannot like it. Other people, or a reviewer, decide.</p>
    <?php elseif ($eligibility['reason'] === 'new'): ?>
        <p>Your account is too new to like listings. This makes it harder to approve a listing with a handful of fresh accounts. Try again after <?= (int) like_min_account_age_days() ?> days.</p>
    <?php elseif ($eligibility['reason'] === 'limit'): ?>
        <p>Too many likes from this connection today. Try again tomorrow.</p>
    <?php else: ?>
        <p>Likes are closed.</p>
    <?php endif; ?>
</section>

<?php if ($endorsements !== []): ?>
    <section class="section">
        <h2>Why people liked it</h2>
        <ul class="endorsement-list">
            <?php foreach ($endorsements as $endorsement): ?>
                <li class="endorsement">
                    <p class="meta"><?= e((string) $endorsement['display_name']) ?> · <?= e(format_date($endorsement['created_at'])) ?></p>
                    <p><?= nl2br(e((string) $endorsement['reason'])) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<p class="meta">Something wrong with this listing? <a href="<?= e($reportUrl) ?>">Report it</a>.</p>
