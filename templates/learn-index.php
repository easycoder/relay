<?php

/** @var array<int, array<string, string>> $guides */

?>
<p class="eyebrow">Learn</p>
<h1>Continuity, in practice</h1>
<p class="lede">Guides to the parts of continuity that are easy to postpone: the handover mechanism, the brief, the dry run, and the people who inherit the work.</p>

<?php if ($guides === []): ?>
    <p>No guides yet.</p>
<?php else: ?>
    <div class="cards">
        <?php foreach ($guides as $guide): ?>
            <article class="card">
                <h3><a href="/learn/<?= e($guide['slug']) ?>/"><?= e($guide['title']) ?></a></h3>
                <p class="pitch"><?= e($guide['description']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
