<?php $pageTitle = 'Forum - #' . $thread['title']; require __DIR__ . '/../../includes/header.php'; ?>
<div class="thread-header">
<h1>
    <?php if ((int)$thread['pinned'] === 1): ?><i data-lucide="pin" class="pin-icon"></i><?php endif; ?>
    <?php if ((int)$thread['locked'] === 1): ?><i data-lucide="lock" class="lock-icon"></i><?php endif; ?>
    <?= e($thread['title']) ?>
</h1>

<?php if ($user): ?>
<form method="post" class="follow-form">
    <?= csrf_field() ?>
    <button type="submit" name="action" value="<?= $isFollowing ? 'unfollow' : 'follow' ?>" class="follow-btn<?= $isFollowing ? ' following' : '' ?>">
        <?= $isFollowing ? 'Following' : 'Follow' ?>
    </button>
</form>
<?php endif; ?>
</div>

<div class="post op">
    <div class="post-meta">
        <a href="<?= e(url('profile/' . rawurlencode($thread['username']))) ?>"><?= e($thread['username']) ?></a>
        <span class="dot">·</span>
        <?= e(date('d.m.Y H:i', (int)$thread['created_at'])) ?>
        <?php if (!empty($thread['edited_at'])): ?><span class="dot">·</span><span class="edited-tag">edited</span><?php endif; ?>
    </div>

    <div class="post-body"><?= render_markdown($thread['body']) ?></div>

    <?php if ($user && ((int)$user['id'] === (int)$thread['user_id'] || ($user['role'] ?? '') === 'admin')): ?>

    <form method="post" class="edit-form<?= $editingThread ? ' open' : '' ?>" id="edit-thread">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="edit-thread-title">Title</label>
            <input
                type="text"
                id="edit-thread-title"
                name="title"
                maxlength="150"
                required
                value="<?= e($editingThread ? ($_POST['title'] ?? '') : $thread['title']) ?>">
        </div>

        <div class="form-group">
            <label for="edit-thread-body">Content</label>
            <textarea
                id="edit-thread-body"
                name="body"
                class="md-input"
                rows="6"
                maxlength="10000"
                required><?= e($editingThread ? ($_POST['body'] ?? '') : $thread['body']) ?></textarea>
            <small>Tip: use #123 to link a thread and @username to mention someone.</small>
        </div>

        <button type="submit" name="action" value="edit_thread" class="cta">Save Changes</button>
    </form>

    <form method="post" class="thread-action-form">
        <?= csrf_field() ?>

        <button
            type="submit"
            name="action"
            value="delete_thread"
            class="action"
            data-confirm="Are you sure you want to delete this thread?"
            title="Delete thread">
            <i data-lucide="trash"></i>
        </button>

        <button
            type="button"
            class="edit-toggle"
            data-target="edit-thread"
            title="Edit thread">
            <i data-lucide="pencil"></i>
        </button>

        <?php if ($user && ($user['role'] ?? '') === 'admin'): ?>

        <button 
            type="submit" 
            name="action" 
            value="lock"
            class="action"
            title="<?= (int)$thread['locked'] === 1 ? 'Unlock thread' : 'Lock thread' ?>">
            <i data-lucide="<?= (int)$thread['locked'] === 1 ? 'unlock' : 'lock' ?>"></i>
        </button>

        <button 
            type="submit" 
            name="action" 
            value="pin"
            class="action"
            title="<?= (int)$thread['pinned'] === 1 ? 'Unpin thread' : 'Pin thread' ?>">
            <i data-lucide="<?= (int)$thread['pinned'] === 1 ? 'pin-off' : 'pin' ?>"></i>
        </button>

        <?php endif; ?>

    </form>
    
    <?php endif; ?>
</div>

<?php foreach ($posts as $p): ?>
<div class="post" id="post-<?= (int)$p['id'] ?>">

    <div class="post-meta">
        <a href="<?= e(url('profile/' . rawurlencode($p['username']))) ?>"><?= e($p['username']) ?></a>
        <span class="dot">·</span>
        <?= e(date('d.m.Y H:i', (int)$p['created_at'])) ?>
        <?php if (!empty($p['edited_at'])): ?><span class="dot">·</span><span class="edited-tag">edited</span><?php endif; ?>
    </div>

    <div class="post-body">
        <?= render_markdown($p['body']) ?>
    </div>

    <?php if ($user && ((int)$user['id'] === (int)$p['user_id'] || ($user['role'] ?? '') === 'admin')): ?>

    <form method="post" class="edit-form<?= $editPostError === (int)$p['id'] ? ' open' : '' ?>" id="edit-post-<?= (int)$p['id'] ?>">
        <?= csrf_field() ?>

        <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">

        <div class="form-group">
            <textarea
                name="body"
                class="md-input"
                rows="5"
                maxlength="10000"
                required><?= e($editPostError === (int)$p['id'] ? ($_POST['body'] ?? '') : $p['body']) ?></textarea>
            <small>Tip: use #123 to link a thread and @username to mention someone.</small>
        </div>

        <button type="submit" name="action" value="edit_post" class="cta">Save</button>
    </form>


    <form method="post" class="thread-action-form">
        <?= csrf_field() ?>

        <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">

        <button 
            type="submit"
            name="action"
            value="delete_post"
            class="action"
            data-confirm="Are you sure you want to delete this post?"
            title="Delete post">
            <i data-lucide="trash"></i>
        </button>

        <button
            type="button"
            class="edit-toggle"
            data-target="edit-post-<?= (int)$p['id'] ?>"
            title="Edit post">
            <i data-lucide="pencil"></i>
        </button>

    </form>
    <?php endif; ?>

</div>
<?php endforeach; ?>

<a id="latest"></a>

<?php if ($errors): ?>
<div class="alert">
    <?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($user && (int)$thread['locked'] === 0): ?>

<form method="post" class="form">
    <?= csrf_field() ?>

    <div class="form-group">
        <label for="body">Reply</label>
        <textarea id="body" name="body" class="md-input" rows="5" maxlength="10000" required></textarea>
        <small>Tip: use #123 to link a thread and @username to mention someone.</small>
    </div>

    <button 
        type="submit" 
        name="action" 
        value="reply" 
        class="cta">
        Reply
    </button>
</form>

<?php elseif ((int)$thread['locked'] === 1): ?>

<p class="hint">
    This thread is locked. New replies are disabled.
</p>

<?php else: ?>

<p class="hint">
    To post a reply, please <a href="<?= e(url('login')) ?>">log in</a>.
</p>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
