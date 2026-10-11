<?php
$pageTitle = page_title($thread['title']);
$__nav = 'home';
require __DIR__ . '/../../includes/header.php';

$isAdmin = $user && ($user['role'] ?? '') === 'admin';
$isLocked = (int)$thread['locked'] === 1;
$canEditThread = can_edit_content($user, (int)$thread['user_id'], (int)$thread['created_at']);
$canManageThread = $user && ((int)$user['id'] === (int)$thread['user_id'] || $isAdmin);
$allowLikes = (bool)site_setting('allow_likes');
$maxTitle = (int)site_setting('max_title_length');
$threadLikes = (int)$likeCounts['thread'];
$pageQuery = $page > 1 ? '?page=' . (int)$page : '';
?>
<div class="breadcrumbs">
    <a href="<?= e(url()) ?>">Threads</a>
    <?php if (!empty($thread['category_name'])): ?>
        <i data-lucide="chevron-right" class="sep"></i>
        <a href="<?= e(url('category/' . $thread['category_slug'])) ?>"><?= e($thread['category_name']) ?></a>
    <?php endif; ?>
</div>

<div class="thread-top">
    <div class="thread-top-row">
        <h1>
            <?php if ((int)$thread['pinned'] === 1): ?><i data-lucide="pin" title="Pinned"></i><?php endif; ?>
            <?php if ($isLocked): ?><i data-lucide="lock" title="Locked"></i><?php endif; ?>
            <span><?= e($thread['title']) ?></span>
        </h1>
        <?php if ($user): ?>
        <form method="post" action="<?= e(url('thread/' . (int)$id)) ?>" class="follow-form">
            <?= csrf_field() ?>
            <button type="submit" name="action" value="<?= $isFollowing ? 'unfollow' : 'follow' ?>" class="follow-btn<?= $isFollowing ? ' following' : '' ?>">
                <i data-lucide="<?= $isFollowing ? 'bell-ring' : 'bell' ?>"></i><?= $isFollowing ? 'Following' : 'Follow' ?>
            </button>
        </form>
        <?php endif; ?>
    </div>
    <div class="thread-info">
        <?php if (!empty($thread['category_name'])): ?>
            <a class="cat-pill" href="<?= e(url('category/' . $thread['category_slug'])) ?>"><?= e($thread['category_name']) ?></a>
        <?php endif; ?>
        <span><i data-lucide="messages-square"></i><?= (int)$totalReplies ?> <?= $totalReplies === 1 ? 'reply' : 'replies' ?></span>
        <?php if (site_setting('count_views')): ?><span><i data-lucide="eye"></i><?= number_format((int)$thread['views']) ?> views</span><?php endif; ?>
        <?php if ($isLocked): ?><span class="badge badge-warn"><i data-lucide="lock"></i>Locked</span><?php endif; ?>
    </div>
</div>

<?php if ($isAdmin): ?>
<div class="mod-bar">
    <span class="mod-label"><i data-lucide="shield"></i>Moderation</span>
    <form method="post" action="<?= e(url('thread/' . (int)$id)) ?>">
        <?= csrf_field() ?>
        <button type="submit" name="action" value="pin" class="btn btn-secondary btn-sm"><i data-lucide="<?= (int)$thread['pinned'] === 1 ? 'pin-off' : 'pin' ?>"></i><?= (int)$thread['pinned'] === 1 ? 'Unpin' : 'Pin' ?></button>
        <button type="submit" name="action" value="lock" class="btn btn-secondary btn-sm"><i data-lucide="<?= $isLocked ? 'lock-open' : 'lock' ?>"></i><?= $isLocked ? 'Unlock' : 'Lock' ?></button>
    </form>
    <form method="post" action="<?= e(url('thread/' . (int)$id)) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="move">
        <div class="select-wrap">
            <select name="category_id" aria-label="Move to category">
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === (int)$thread['category_id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <i data-lucide="chevron-down"></i>
        </div>
        <button type="submit" class="btn btn-secondary btn-sm"><i data-lucide="arrow-right-left"></i>Move</button>
    </form>
</div>
<?php endif; ?>

<?php if ($page === 1): ?>
<article class="post op" id="thread-body">
    <div class="post-author">
        <a href="<?= e(url('profile/' . rawurlencode($thread['username']))) ?>"><?= avatar($thread['username'], 'lg') ?></a>
        <a class="author-name" href="<?= e(url('profile/' . rawurlencode($thread['username']))) ?>"><?= e($thread['username']) ?></a>
        <?= role_badge($thread['user_role']) ?>
        <div class="author-stats"><span>Joined <?= e(date('M Y', (int)$thread['user_joined'])) ?></span></div>
    </div>
    <div class="post-content">
        <div class="post-meta">
            <span>
                <?= time_tag((int)$thread['created_at'], true) ?>
                <?php if (!empty($thread['edited_at'])): ?><span class="dot">·</span><span class="edited-tag" title="<?= e(date('M j, Y · H:i', (int)$thread['edited_at'])) ?>">edited</span><?php endif; ?>
            </span>
            <span class="post-number">#1</span>
        </div>

        <div class="post-body" id="thread-text"><?= render_markdown($thread['body']) ?></div>

        <?php if ($canEditThread): ?>
        <form method="post" action="<?= e(url('thread/' . (int)$id)) ?>" class="edit-form<?= $editingThread ? ' open' : '' ?>" id="edit-thread">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="edit-thread-title">Title</label>
                <input type="text" id="edit-thread-title" name="title" maxlength="<?= $maxTitle ?>" required value="<?= e($editingThread ? ($_POST['title'] ?? '') : $thread['title']) ?>">
            </div>
            <div class="form-group">
                <label for="edit-thread-body">Content</label>
                <?php $editorId = 'edit-thread-body'; $editorName = 'body'; $editorValue = $editingThread ? ($_POST['body'] ?? '') : $thread['body']; $editorRows = 8; require __DIR__ . '/partials/editor.php'; ?>
            </div>
            <div class="form-actions">
                <button type="submit" name="action" value="edit_thread" class="btn btn-primary btn-sm">Save changes</button>
                <button type="button" class="btn btn-ghost btn-sm edit-toggle" data-target="edit-thread">Cancel</button>
            </div>
        </form>
        <?php endif; ?>

        <div class="post-footer">
            <?php if (!$allowLikes): ?>
            <?php elseif ($user && (int)$user['id'] !== (int)$thread['user_id']): ?>
                <form method="post" action="<?= e(url('thread/' . (int)$id) . $pageQuery) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" name="action" value="like" class="like-btn<?= $liked['thread'] ? ' liked' : '' ?>" title="<?= $liked['thread'] ? 'Remove like' : 'Like' ?>">
                        <i data-lucide="heart"></i><?= $threadLikes ?: 'Like' ?>
                    </button>
                </form>
            <?php elseif ($threadLikes > 0): ?>
                <span class="like-btn" title="Likes"><i data-lucide="heart"></i><?= $threadLikes ?></span>
            <?php endif; ?>
            <?php if ($user && !$isLocked): ?>
                <button type="button" class="action-btn quote-btn" data-source="thread-text" data-author="<?= e($thread['username']) ?>"><i data-lucide="quote"></i>Quote</button>
            <?php endif; ?>
            <span class="spacer"></span>
            <?php if ($canEditThread): ?>
                <button type="button" class="action-btn edit-toggle" data-target="edit-thread"><i data-lucide="pencil"></i>Edit</button>
            <?php endif; ?>
            <?php if ($canManageThread): ?>
                <form method="post" action="<?= e(url('thread/' . (int)$id)) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" name="action" value="delete_thread" class="action-btn destructive" data-confirm="Are you sure you want to delete this thread? This cannot be undone."><i data-lucide="trash-2"></i>Delete</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</article>
<?php endif; ?>

<?php foreach ($posts as $idx => $p):
    $canEdit = can_edit_content($user, (int)$p['user_id'], (int)$p['created_at']);
    $canManage = $user && ((int)$user['id'] === (int)$p['user_id'] || $isAdmin);
    $pid = (int)$p['id'];
    $likes = $likeCounts['post'][$pid] ?? 0;
    $isLiked = !empty($liked['post'][$pid]);
?>
<article class="post<?= $editPostError === $pid ? ' editing' : '' ?>" id="post-<?= $pid ?>">
    <div class="post-author">
        <a href="<?= e(url('profile/' . rawurlencode($p['username']))) ?>"><?= avatar($p['username'], 'lg') ?></a>
        <a class="author-name" href="<?= e(url('profile/' . rawurlencode($p['username']))) ?>"><?= e($p['username']) ?></a>
        <?= role_badge($p['user_role']) ?>
        <div class="author-stats">
            <span><?= number_format((int)$p['user_post_count']) ?> replies</span>
            <span>Joined <?= e(date('M Y', (int)$p['user_joined'])) ?></span>
        </div>
    </div>
    <div class="post-content">
        <div class="post-meta">
            <span>
                <?= time_tag((int)$p['created_at'], true) ?>
                <?php if (!empty($p['edited_at'])): ?><span class="dot">·</span><span class="edited-tag" title="<?= e(date('M j, Y · H:i', (int)$p['edited_at'])) ?>">edited</span><?php endif; ?>
            </span>
            <a class="post-number" href="#post-<?= $pid ?>" title="Permalink">#<?= (int)($offset + $idx + 2) ?></a>
        </div>

        <div class="post-body" id="post-text-<?= $pid ?>"><?= render_markdown($p['body']) ?></div>

        <?php if ($canEdit): ?>
        <form method="post" action="<?= e(url('thread/' . (int)$id) . $pageQuery) ?>" class="edit-form<?= $editPostError === $pid ? ' open' : '' ?>" id="edit-post-<?= $pid ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="post_id" value="<?= $pid ?>">
            <div class="form-group">
                <?php $editorId = 'edit-body-' . $pid; $editorName = 'body'; $editorValue = $editPostError === $pid ? ($_POST['body'] ?? '') : $p['body']; $editorRows = 5; require __DIR__ . '/partials/editor.php'; ?>
            </div>
            <div class="form-actions">
                <button type="submit" name="action" value="edit_post" class="btn btn-primary btn-sm">Save</button>
                <button type="button" class="btn btn-ghost btn-sm edit-toggle" data-target="edit-post-<?= $pid ?>">Cancel</button>
            </div>
        </form>
        <?php endif; ?>

        <div class="post-footer">
            <?php if (!$allowLikes): ?>
            <?php elseif ($user && (int)$user['id'] !== (int)$p['user_id']): ?>
                <form method="post" action="<?= e(url('thread/' . (int)$id) . $pageQuery) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="post_id" value="<?= $pid ?>">
                    <button type="submit" name="action" value="like" class="like-btn<?= $isLiked ? ' liked' : '' ?>" title="<?= $isLiked ? 'Remove like' : 'Like' ?>">
                        <i data-lucide="heart"></i><?= $likes ?: 'Like' ?>
                    </button>
                </form>
            <?php elseif ($likes > 0): ?>
                <span class="like-btn" title="Likes"><i data-lucide="heart"></i><?= (int)$likes ?></span>
            <?php endif; ?>
            <?php if ($user && !$isLocked): ?>
                <button type="button" class="action-btn quote-btn" data-source="post-text-<?= $pid ?>" data-author="<?= e($p['username']) ?>"><i data-lucide="quote"></i>Quote</button>
            <?php endif; ?>
            <span class="spacer"></span>
            <?php if ($canEdit): ?>
                <button type="button" class="action-btn edit-toggle" data-target="edit-post-<?= $pid ?>"><i data-lucide="pencil"></i>Edit</button>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <form method="post" action="<?= e(url('thread/' . (int)$id) . $pageQuery) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="post_id" value="<?= $pid ?>">
                    <button type="submit" name="action" value="delete_post" class="action-btn destructive" data-confirm="Are you sure you want to delete this reply?"><i data-lucide="trash-2"></i>Delete</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</article>
<?php endforeach; ?>

<?= pagination($page, $pages, url('thread/' . (int)$id)) ?>

<a id="latest"></a>

<?php if ($errors): ?>
<div class="alert alert-spaced" role="alert">
    <?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($user && (!$isLocked || $isAdmin)): ?>
<section class="reply-box" id="reply">
    <div class="post-author">
        <?= avatar($user['username'], 'lg') ?>
        <span class="author-name"><?= e($user['username']) ?></span>
    </div>
    <form method="post" action="<?= e(url('thread/' . (int)$id) . $pageQuery) ?>" class="reply-form">
        <?= csrf_field() ?>
        <h2>Post a reply</h2>
        <?php if ($isLocked): ?><p class="alert alert-warning">This thread is locked. Only admins can reply.</p><?php endif; ?>
        <div class="form-group">
            <?php $editorId = 'reply-body'; $editorName = 'body'; $editorValue = ($errors && ($_POST['action'] ?? 'reply') === 'reply') ? ($_POST['body'] ?? '') : ''; $editorRows = 5; require __DIR__ . '/partials/editor.php'; ?>
        </div>
        <button type="submit" name="action" value="reply" class="btn btn-primary">Post reply</button>
    </form>
</section>
<?php elseif ($isLocked): ?>
<div class="reply-notice"><i data-lucide="lock"></i>This thread is locked. New replies are disabled.</div>
<?php else: ?>
<div class="reply-notice">
    <a href="<?= e(url('login')) ?>">Log in</a><?php if ($__canRegister): ?> or <a href="<?= e(url('register')) ?>">sign up</a><?php endif; ?> to join the discussion.
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
