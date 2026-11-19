<?php

/** @var array<int, array<string, mixed>> $projects */

$count = count($projects);

?>
<p class="eyebrow">Catalogue</p>
<h1>Ideas, prototypes and projects</h1>
<p class="lede">Fewer, better listings. <?= $count ?> listing<?= $count === 1 ? '' : 's' ?> so far, across ideas, prototypes and live projects.</p>

<p class="actions">
    <a class="button button-secondary" href="/find/">Filter and search</a>
    <a class="button button-secondary" href="/find/?view=pending">See what is waiting for approval</a>
</p>

<?php if ($projects === []): ?>
    <p>No listings yet.</p>
<?php else: ?>
    <div class="cards">
        <?php foreach ($projects as $project): ?>
            <?php require __DIR__ . '/partials/card.php'; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
