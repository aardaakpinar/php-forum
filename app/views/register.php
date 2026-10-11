<?php $pageTitle = page_title('Sign up'); require __DIR__ . '/../../includes/header.php'; ?>
<?php if (!empty($closed)): ?>
<div class="auth-wrap">
<div class="card auth-card">
    <h1>Registration closed</h1>
    <p class="lead"><?= e((string)site_setting('registration_closed_message')) ?></p>
    <a class="btn btn-secondary btn-block" href="<?= e(url('login')) ?>">Log in</a>
</div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; return; ?>
<?php endif; ?>
<div class="auth-wrap">
<div class="card auth-card">
    <h1>Create your account</h1>
    <p class="lead">Join <?= e(site_settings()['name']) ?> and start taking part in discussions.</p>

    <?php if ($errors): ?>
    <div class="alert" role="alert">
        <?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post" class="form" novalidate>
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= e($username) ?>" maxlength="20" required autocomplete="username" autofocus>
            <small>3–20 characters: letters, numbers and underscores.</small>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="254" required autocomplete="email">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" maxlength="200" required autocomplete="new-password">
            <small><?= e(password_hint()) ?></small>
        </div>
        <div class="form-group">
            <label for="password2">Confirm password</label>
            <input type="password" id="password2" name="password2" maxlength="200" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Create account</button>
    </form>
    <p class="auth-alt">Already have an account? <a href="<?= e(url('login')) ?>">Log in</a></p>
</div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
