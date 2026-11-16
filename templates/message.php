<?php

/** @var string $heading */
/** @var string $message */
/** @var string $link */
/** @var string $linkText */

?>
<section class="hero auth-hero">
    <p class="eyebrow">Relay</p>
    <h1><?= e($heading) ?></h1>
    <p class="lede"><?= e($message) ?></p>
    <p class="actions"><a class="button" href="<?= e($link) ?>"><?= e($linkText) ?></a></p>
</section>
