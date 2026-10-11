<?php $pageTitle = page_title('Log in'); require __DIR__ . '/../../includes/header.php'; ?>
<div class="auth-wrap">
<div class="card auth-card">
    <h1>Welcome back</h1>
    <p class="lead">Log in to your <?= e(site_settings()['name']) ?> account.</p>

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
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" maxlength="200" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>
    <?php if ($__canRegister): ?><p class="auth-alt">Don't have an account? <a href="<?= e(url('register')) ?>">Sign up</a></p><?php endif; ?>
</div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
