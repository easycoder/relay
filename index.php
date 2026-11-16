<?php

/**
 * Front controller when the repository itself is the web root.
 *
 * The preferred layout points DreamHost's Web Directory at public/. While
 * that is being sorted out, this file makes "/" work from the repository
 * root without relying on mod_rewrite. It is outside the web root in the
 * preferred layout, so it is harmless there.
 */

declare(strict_types=1);

require __DIR__ . '/public/index.php';
