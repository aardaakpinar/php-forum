<?php $pageTitle = 'Forum - New Thread'; require __DIR__ . '/../../includes/header.php'; ?>
<h1>New Thread</h1>

<?php if ($errors): ?>
<div class="alert">
    <?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" class="form">
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= e($title) ?>" maxlength="150" required>
    </div>
    <div class="form-group">
        <label for="body">Content</label>
        <textarea id="body" name="body" class="md-input" rows="8" maxlength="10000" required><?= e($body) ?></textarea>
        <small>Tip: use #123 to link a thread and @username to mention someone.</small>
    </div>
    <button type="submit" class="cta">Create Thread</button>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
