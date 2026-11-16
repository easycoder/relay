<?php

/** @var array<string, string> $guide */
/** @var string $body */

?>
<article class="guide">
    <p class="meta"><a href="/learn/">← All guides</a></p>

    <header class="listing-header">
        <p class="eyebrow">Guide</p>
        <h1><?= e($guide['title']) ?></h1>
        <p class="lede"><?= e($guide['description']) ?></p>
    </header>

    <div class="guide-body">
        <?= $body ?>
    </div>
</article>
