<?php
$pageTitle = page_title('Settings');
$__nav = 'settings';
require __DIR__ . '/../../includes/header.php';

$formAction = url('settings?tab=' . $tab);
$activeSection = $schema[$tab] ?? null;
?>
<div class="page-header"><div><h1>Settings</h1><p class="lead">Manage your profile, preferences and account security.</p></div></div>

<div class="settings-layout">
    <nav class="settings-nav" aria-label="Settings sections">
        <?php foreach ($tabs as $id => $t): ?>
            <?php if ($id === 'data'): ?><div class="nav-sep"></div><?php endif; ?>
            <a href="<?= e(url('settings?tab=' . $id)) ?>" class="<?= $tab === $id ? 'active' : '' ?>"<?= $tab === $id ? ' aria-current="page"' : '' ?>><i data-lucide="<?= e($t['icon']) ?>"></i><?= e($t['label']) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="settings-content">
        <?php if ($errors): ?>
        <div class="alert" role="alert"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success" role="status"><p><?= e($success) ?></p></div>
        <?php endif; ?>

<?php if ($tab === 'profile'): ?>
        <section class="card settings-panel">
            <div class="settings-panel-head"><h2>Profile</h2><p>Shown on your public profile page.</p></div>
            <form method="post" action="<?= e($formAction) ?>" class="settings-form">
                <?= csrf_field() ?>
                <div class="settings-panel-body">
                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="3" maxlength="300" placeholder="Tell the community a little about yourself"><?= e($user['bio'] ?? '') ?></textarea>
                        <small>Up to 300 characters.</small>
                    </div>
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" maxlength="60" value="<?= e($user['location'] ?? '') ?>" placeholder="City, Country">
                    </div>
                    <div class="form-group">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" maxlength="100" value="<?= e($user['website'] ?? '') ?>" placeholder="https://example.com" inputmode="url" autocomplete="url">
                        <small>Must start with http:// or https://</small>
                    </div>
                </div>
                <div class="settings-panel-foot">
                    <button type="submit" name="action" value="update_profile" class="btn btn-primary">Save profile</button>
                    <a href="<?= e(url('profile/' . rawurlencode($user['username']))) ?>" class="btn btn-ghost">View profile</a>
                </div>
            </form>
        </section>

<?php elseif ($tab === 'account'): ?>
        <section class="card settings-panel">
            <div class="settings-panel-head"><h2>Email address</h2><p>Your email is private. Current address: <strong><?= e($user['email']) ?></strong></p></div>
            <form method="post" action="<?= e($formAction) ?>" class="settings-form">
                <?= csrf_field() ?>
                <div class="settings-panel-body">
                    <div class="form-group">
                        <label for="email">New email address</label>
                        <input type="email" id="email" name="email" maxlength="254" required autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="email_password">Current password</label>
                        <input type="password" id="email_password" name="current_password" maxlength="200" required autocomplete="current-password">
                    </div>
                </div>
                <div class="settings-panel-foot">
                    <button type="submit" name="action" value="change_email" class="btn btn-primary">Update email</button>
                </div>
            </form>
        </section>

        <section class="card settings-panel">
            <div class="settings-panel-head"><h2>Change password</h2><p>Use a strong password you don't use elsewhere.</p></div>
            <form method="post" action="<?= e($formAction) ?>" class="settings-form">
                <?= csrf_field() ?>
                <div class="settings-panel-body">
                    <div class="form-group">
                        <label for="current_password">Current password</label>
                        <input type="password" id="current_password" name="current_password" maxlength="200" required autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label for="new_password">New password</label>
                        <input type="password" id="new_password" name="new_password" maxlength="200" required autocomplete="new-password">
                        <small><?= e(password_hint()) ?></small>
                    </div>
                    <div class="form-group">
                        <label for="new_password2">Confirm new password</label>
                        <input type="password" id="new_password2" name="new_password2" maxlength="200" required autocomplete="new-password">
                    </div>
                </div>
                <div class="settings-panel-foot">
                    <button type="submit" name="action" value="change_password" class="btn btn-primary">Update password</button>
                </div>
            </form>
        </section>

<?php elseif ($activeSection): ?>
        <section class="card settings-panel">
            <div class="settings-panel-head"><h2><?= e($activeSection['label']) ?></h2><p><?= e($activeSection['description']) ?></p></div>
            <form method="post" action="<?= e($formAction) ?>" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="<?= e($tab) ?>">
                <div class="setting-list">
                    <?php foreach ($activeSection['fields'] as $key => $def): ?>
                        <?= render_setting_field($key, $def, $prefs[$key], 'prefs') ?>
                    <?php endforeach; ?>
                </div>
                <div class="settings-panel-foot">
                    <button type="submit" name="action" value="save_prefs" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </section>

<?php else: /* data */ ?>
        <section class="card settings-panel">
            <div class="settings-panel-head"><h2>Export your data</h2><p>Download your profile, preferences, threads, replies, follows and likes as a JSON file.</p></div>
            <form method="post" action="<?= e($formAction) ?>" class="settings-form">
                <?= csrf_field() ?>
                <div class="settings-panel-foot">
                    <button type="submit" name="action" value="export_data" class="btn btn-secondary"><i data-lucide="download"></i>Download my data</button>
                </div>
            </form>
        </section>

        <section class="card settings-panel danger-zone">
            <div class="settings-panel-head"><h2>Delete account</h2><p>Permanently deletes your account and all of your threads and replies. This cannot be undone.</p></div>
            <form method="post" action="<?= e($formAction) ?>" class="settings-form" data-confirm="This will permanently delete your account. Are you sure?">
                <?= csrf_field() ?>
                <div class="settings-panel-body">
                    <div class="form-group">
                        <label for="confirm_password">Confirm with your password</label>
                        <input type="password" id="confirm_password" name="confirm_password" maxlength="200" required autocomplete="current-password">
                    </div>
                </div>
                <div class="settings-panel-foot">
                    <button type="submit" name="action" value="delete_account" class="btn btn-danger">Delete my account</button>
                </div>
            </form>
        </section>
<?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
