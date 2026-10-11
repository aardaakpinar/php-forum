<?php $pageTitle = page_title('New thread'); require __DIR__ . '/../../includes/header.php'; ?>
<div class="breadcrumbs">
    <a href="<?= e(url()) ?>">Threads</a><i data-lucide="chevron-right" class="sep"></i><span>New thread</span>
</div>
<div class="page-header"><div><h1>Start a new thread</h1><p class="lead">Share a question, idea or announcement with the community.</p></div></div>

<?php if ($errors): ?>
<div class="alert" role="alert">
    <?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" class="form card card-pad">
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="category_id">Category</label>
        <div class="select-wrap">
            <select id="category_id" name="category_id" required>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === (int)$categoryId ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <i data-lucide="chevron-down"></i>
        </div>
    </div>
    <div class="form-group">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= e($title) ?>" maxlength="<?= (int)site_setting('max_title_length') ?>" required placeholder="A clear, descriptive title" autofocus>
    </div>
    <div class="form-group">
        <label for="body">Content</label>
        <?php $editorId = 'body'; $editorName = 'body'; $editorValue = $body; $editorRows = 10; require __DIR__ . '/partials/editor.php'; ?>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Publish thread</button>
        <a href="<?= e(url()) ?>" class="btn btn-ghost">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
