<?php

/** @var string $title */
/** @var string $description */
/** @var string $canonical */
/** @var string $content */
/** @var string $siteUrl */
/** @var array<string, mixed>|null $currentUser */

?>
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <?php if (!empty($noindex)): ?>
        <meta name="robots" content="noindex">
    <?php endif; ?>
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
                <a href="/submit/">Submit</a>
                <a href="/dashboard/" id="relay-nav-dashboard" <?= empty($currentUser) ? 'hidden' : '' ?>>Dashboard</a>
                <a href="/login/" id="relay-nav-signin" <?= !empty($currentUser) ? 'hidden' : '' ?>>Sign in</a>
                <span class="nav-user" id="relay-nav-user" <?= empty($currentUser) ? 'hidden' : '' ?>><?php if (!empty($currentUser)): ?><?= e($currentUser['display_name']) ?> (<?= e($currentUser['email']) ?>)<?php endif; ?></span>
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

    <script>
    (function () {
        if (!window.fetch) return;
        fetch('/session.php', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (!data || !data.user) return;
                var user = document.getElementById('relay-nav-user');
                var dashboard = document.getElementById('relay-nav-dashboard');
                var signin = document.getElementById('relay-nav-signin');
                if (user) {
                    user.textContent = data.user.display_name + ' (' + data.user.email + ')';
                    user.hidden = false;
                }
                if (dashboard) dashboard.hidden = false;
                if (signin) signin.hidden = true;
            })
            .catch(function () {});
    })();
    </script>
</body>
</html>
