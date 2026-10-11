<?php $pageTitle = page_title('Log out'); require __DIR__ . '/../../includes/header.php'; ?>
<div class="auth-wrap">
<div class="card auth-card">
    <h1>Log out</h1>
    <p class="lead">Are you sure you want to log out?</p>
    <form method="post" class="form">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary btn-block">Yes, log out</button>
    </form>
    <p class="auth-alt"><a href="<?= e(url()) ?>">Cancel</a></p>
</div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
