<?php $pageTitle = 'Forum - Register'; require __DIR__ . '/../../includes/header.php'; ?>
<h1>Register</h1>

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
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="254" required autocomplete="email">
    </div>
    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" maxlength="200" required autocomplete="new-password">
        <small>At least 10 characters; uppercase letter, lowercase letter, and number required.</small>
    </div>
    <div class="form-group">
        <label for="password2">Password (repeat)</label>
        <input type="password" id="password2" name="password2" maxlength="200" required autocomplete="new-password">
    </div>
    <button type="submit" class="cta">Register</button>
</form>
<p class="hint">Already have an account? <a href="<?= e(url('login')) ?>">Login</a></p>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
