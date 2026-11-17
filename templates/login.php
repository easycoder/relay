<?php

/** @var array<int, string> $errors */
/** @var string $email */
/** @var string $name */
/** @var bool $sent */
/** @var string $csrf */
/** @var array<int, array<string, mixed>> $accounts */
/** @var array<string, mixed>|null $signedIn */

$name = $name ?? '';
$accounts = $accounts ?? [];
$signedIn = $signedIn ?? null;

?>
<section class="hero auth-hero">
    <p class="eyebrow">Sign in</p>
    <h1>Sign in to Relay</h1>
    <p class="lede">There are no passwords. Enter your email address and we will send a single-use link.</p>
</section>

<?php if ($signedIn !== null): ?>
    <p class="notice">You are signed in as <?= e($signedIn['display_name']) ?> (<?= e($signedIn['email']) ?>). Signing in below adds that account to this browser; this one stays signed in and remains under <a href="/accounts/">Accounts</a>.</p>
    <p class="actions"><a class="button button-secondary" href="/dashboard/">Back to the dashboard</a></p>
<?php endif; ?>

<?php if ($sent): ?>
    <p class="notice">If that address is known, a sign-in link is on its way. It expires in 15 minutes.</p>
<?php endif; ?>

<?php if ($accounts !== []): ?>
    <section class="section accounts-recall">
        <h2><?= $signedIn === null ? 'Continue as' : 'Switch to another account' ?></h2>
        <p class="meta">These accounts are still signed in on this browser, so no link is needed.</p>

        <div class="actions">
            <?php foreach ($accounts as $index => $account): ?>
                <form class="inline-form" method="post" action="/accounts/">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="switch">
                    <input type="hidden" name="account" value="<?= (int) $index ?>">
                    <button class="button button-secondary" type="submit"><?= e($account['name']) ?><?= $account['role'] === 'user' ? '' : ' (' . e(ucfirst((string) $account['role'])) . ')' ?></button>
                </form>
            <?php endforeach; ?>
            <a class="button button-secondary" href="/accounts/">All accounts</a>
        </div>
    </section>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="form-errors" role="alert">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form class="form" method="post" action="/login/">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

    <div class="honeypot" hidden aria-hidden="true">
        <label>Leave this field empty <input type="text" name="relay_hp" tabindex="-1" autocomplete="off"></label>
    </div>

    <label class="field">
        <span>Your name</span>
        <input type="text" name="name" value="<?= e($name) ?>" maxlength="100" autocomplete="name">
        <small>For a new account, created only when you use the link. If you already have one, we keep the name you chose before.</small>
    </label>

    <label class="field">
        <span>Email address</span>
        <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email" autofocus>
    </label>

    <button class="button" type="submit">Send sign-in link</button>
</form>

<p class="meta">The first sign-in creates an account. The link works once and expires after 15 minutes.</p>
