<?php

/** @var array<string, mixed> $project */

$isPending = ($project['review_status'] ?? 'approved') === 'pending';
$stage = (string) ($project['stage'] ?? 'live');

$url = $isPending
    ? '/waiting/?slug=' . urlencode((string) $project['slug'])
    : '/projects/' . rawurlencode((string) $project['slug']) . '/';

?>
<article class="card">
    <p class="badges">
        <span class="badge"><?= e(stage_label($stage)) ?></span>
        <span class="badge badge-quiet"><?= e(status_label($project['status'])) ?></span>
        <?php if (edit_marker_visible($project)): ?>
            <span class="badge badge-quiet">Edited</span>
        <?php endif; ?>
        <?php if ($isPending): ?>
            <span class="badge badge-quiet">Pending approval</span>
        <?php else: ?>
            <span class="badge badge-quiet">Approved</span>
        <?php endif; ?>
        <?php if (!empty($project['is_draft'])): ?>
            <span class="badge badge-quiet">Draft</span>
        <?php endif; ?>
        <?php if ($stage !== 'idea' && isset($project['readiness'])): ?>
            <span class="badge badge-quiet"><?= e(readiness_label((int) $project['readiness'])) ?></span>
        <?php endif; ?>
    </p>
    <h3><a href="<?= e($url) ?>"><?= e($project['title']) ?></a></h3>
    <p class="pitch"><?= e($project['pitch']) ?></p>
    <p class="card-meta">
        <?= e($project['category_name']) ?><?php if (!empty($project['arrangement'])): ?> · <?= e(arrangement_label($project['arrangement'])) ?><?php endif; ?><?php $likeLabel = like_count_label((int) ($project['like_count'] ?? 0)); ?><?php if ($likeLabel !== ''): ?> · <?= e($likeLabel) ?><?php endif; ?>
    </p>
</article>
