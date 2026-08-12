<?php $pageTitle = 'Forum - Settings'; require __DIR__ . '/../../includes/header.php'; ?>
<h1>Settings</h1>

<?php if ($errors): ?>
<div class="alert">
    <?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><p><?= e($success) ?></p></div>
<?php endif; ?>

<h2>Change Password</h2>
<form method="post" class="form">
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" maxlength="200" required autocomplete="current-password">
    </div>
    <div class="form-group">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" maxlength="200" required autocomplete="new-password">
        <small>At least 10 characters; uppercase letter, lowercase letter, and number required.</small>
    </div>
    <div class="form-group">
        <label for="new_password2">New Password (repeat)</label>
        <input type="password" id="new_password2" name="new_password2" maxlength="200" required autocomplete="new-password">
    </div>
    <button type="submit" name="action" value="change_password" class="cta">Update Password</button>
</form>

<h2>Delete Account</h2>
<p class="hint">This will permanently delete your account and all of your threads and replies. This cannot be undone.</p>
<form method="post" class="form" data-confirm="This will permanently delete your account. Are you sure?">
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="confirm_password">Password</label>
        <input type="password" id="confirm_password" name="confirm_password" maxlength="200" required autocomplete="current-password">
    </div>
    <button type="submit" name="action" value="delete_account" class="danger">Delete My Account</button>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
