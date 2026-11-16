<?php

/** @var string $title */
/** @var string $description */
/** @var string $canonical */
/** @var string $content */
/** @var string $siteUrl */

?>
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="site-header">
        <div class="wrap header-inner">
            <a class="brand" href="/">Relay</a>
            <nav aria-label="Primary">
                <a href="/projects/">Projects</a>
                <a href="/learn/">Learn</a>
                <a href="<?= e(RELAY_REPO_URL) ?>" rel="noopener">Repository</a>
            </nav>
        </div>
    </header>

    <main class="wrap">
        <?= $content ?>
    </main>

    <footer class="site-footer">
        <div class="wrap">
            <p>Relay is free and open source under the Apache-2.0 licence.</p>
            <p><a href="/projects/">Projects</a> · <a href="<?= e(RELAY_REPO_URL) ?>" rel="noopener">GitHub</a></p>
        </div>
    </footer>
</body>
</html>
