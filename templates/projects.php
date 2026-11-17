<?php

/** @var array<int, array<string, mixed>> $projects */

$count = count($projects);

?>
<p class="eyebrow">Catalogue</p>
<h1>Projects seeking a next maintainer</h1>
<p class="lede">Fewer, better listings. <?= $count ?> listing<?= $count === 1 ? '' : 's' ?> so far; the first one is the project itself.</p>

<p class="actions"><a class="button button-secondary" href="/find/">Filter and search</a></p>

<?php if ($projects === []): ?>
    <p>No listings yet.</p>
<?php else: ?>
    <div class="cards">
        <?php foreach ($projects as $project): ?>
            <?php require __DIR__ . '/partials/card.php'; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
