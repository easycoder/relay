<?php

/** @var array<string, mixed>|null $user */
/** @var array<int, array<string, mixed>> $accounts */
/** @var string $csrf */
/** @var string $error */
/** @var bool $forgotten */

$user = $user ?? null;
$accounts = $accounts ?? [];
$error = $error ?? '';
$forgotten = $forgotten ?? false;

?>
<section class="hero auth-hero">
    <p class="eyebrow">Accounts</p>
    <h1>Accounts on this browser</h1>

    <?php if ($user !== null): ?>
        <p class="lede">Signed in as <?= e($user['display_name']) ?> (<?= e($user['email']) ?>).</p>
    <?php else: ?>
        <p class="lede">You are signed out here. These accounts can come back without another email.</p>
    <?php endif; ?>
</section>

<?php if ($error !== ''): ?>
    <div class="form-errors" role="alert">
        <ul>
            <li><?= e($error) ?></li>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($forgotten)): ?>
    <p class="notice">This browser has forgotten every account and ended its session. <a href="/login/">Sign in</a> when you want to come back.</p>
<?php endif; ?>

<?php if ($accounts === []): ?>
    <p class="section">No other accounts are remembered in this browser. <a href="/login/">Sign in</a> once and it will be kept here for next time.</p>
<?php else: ?>
    <section class="section">
        <h2>Switch to</h2>

        <div class="cards">
            <?php foreach ($accounts as $index => $account): ?>
                <article class="card">
                    <p class="badges">
                        <span class="badge badge-quiet"><?= e(ucfirst((string) $account['role'])) ?></span>
                    </p>
                    <h3><?= e($account['name']) ?></h3>
                    <p class="card-meta">
                        <?= e($account['email']) ?>
                        <?php if ((int) $account['expires'] > 0): ?>
                            · session until <?= e(date('j M Y', (int) $account['expires'])) ?>
                        <?php endif; ?>
                    </p>

                    <div class="actions">
                        <form class="inline-form" method="post" action="/accounts/">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="switch">
                            <input type="hidden" name="account" value="<?= (int) $index ?>">
                            <button class="button" type="submit">Switch to <?= e($account['name']) ?></button>
                        </form>

                        <form class="inline-form" method="post" action="/accounts/">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="forget">
                            <input type="hidden" name="account" value="<?= (int) $index ?>">
                            <button class="button button-secondary" type="submit">Forget</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<div class="actions section">
    <a class="button button-secondary" href="/login/">Sign in as someone else</a>

    <?php if ($user !== null || $accounts !== []): ?>
        <form class="inline-form" method="post" action="/accounts/">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="forget-browser">
            <button class="button button-secondary" type="submit">Forget this browser</button>
        </form>
    <?php endif; ?>
</div>

<p class="meta">A session lasts thirty days from its last use, so an account you keep using stays signed in and one you leave alone lapses. Sign out ends the account you are using and leaves the others here; <strong>Forget this browser</strong> drops all of them. Tokens are kept in cookies the page's own scripts cannot read.</p>
