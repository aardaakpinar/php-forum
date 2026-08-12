<?php $pageTitle = 'Forum - Logout'; require __DIR__ . '/../../includes/header.php'; ?>
<h1>Logout</h1>
<form method="post" class="form">
    <?= csrf_field() ?>
    <p class="hint">Are you sure you want to logout?</p>
    <button type="submit" class="cta">Yes, Logout</button>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
