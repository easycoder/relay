<?php

/** @var array<string, mixed> $project */

?>
<article class="card">
    <p class="badges">
        <span class="badge"><?= e(status_label($project['status'])) ?></span>
        <?php if (!empty($project['is_draft'])): ?>
            <span class="badge badge-quiet">Draft</span>
        <?php endif; ?>
        <span class="badge badge-quiet"><?= e(readiness_label((int) $project['readiness'])) ?></span>
    </p>
    <h3><a href="/projects/<?= e($project['slug']) ?>/"><?= e($project['title']) ?></a></h3>
    <p class="pitch"><?= e($project['pitch']) ?></p>
    <p class="card-meta">
        <?= e($project['category_name']) ?><?php if (!empty($project['arrangement'])): ?> · <?= e(arrangement_label($project['arrangement'])) ?><?php endif; ?>
    </p>
</article>
