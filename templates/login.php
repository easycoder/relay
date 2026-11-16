<?php

/** @var array<int, string> $errors */
/** @var string $email */
/** @var string $name */
/** @var bool $sent */
/** @var string $csrf */

$name = $name ?? '';

?>
<section class="hero auth-hero">
    <p class="eyebrow">Sign in</p>
    <h1>Sign in to Relay</h1>
    <p class="lede">There are no passwords. Enter your email address and we will send a single-use link.</p>
</section>

<?php if ($sent): ?>
    <p class="notice">If that address is known, a sign-in link is on its way. It expires in 15 minutes.</p>
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
        <small>For a new account. If you already have one, we keep the name you chose before.</small>
    </label>

    <label class="field">
        <span>Email address</span>
        <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email" autofocus>
    </label>

    <button class="button" type="submit">Send sign-in link</button>
</form>

<p class="meta">The first sign-in creates an account. The link works once and expires after 15 minutes.</p>
