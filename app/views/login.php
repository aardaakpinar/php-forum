<?php $pageTitle = 'Forum - Login'; require __DIR__ . '/../../includes/header.php'; ?>
<h1>Login</h1>

<?php if ($errors): ?>
<div class="alert">
    <?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" class="form" novalidate>
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>" maxlength="20" required autocomplete="username">
    </div>
    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" maxlength="200" required autocomplete="current-password">
    </div>
    <button type="submit" class="cta">Login</button>
</form>
<p class="hint">Don't have an account? <a href="<?= e(url('register')) ?>">Register</a></p>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
