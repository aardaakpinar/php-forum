<?php $pageTitle = page_title('Notifications'); require __DIR__ . '/../../includes/header.php';
$unread = 0; foreach ($notes as $n) { if ((int)$n['is_read'] === 0) { $unread++; } }
?>
<div class="page-header">
    <div><h1>Notifications</h1><p class="lead"><?= $unread ? $unread . ' unread' : 'You\'re all caught up' ?></p></div>
    <?php if ($unread): ?>
    <form method="post" class="page-actions">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn btn-secondary btn-sm"><i data-lucide="check"></i>Mark all as read</button>
    </form>
    <?php endif; ?>
</div>

<?php if (!$notes): ?>
    <div class="card empty-state"><i data-lucide="bell"></i><h3>No notifications</h3><p>You'll be notified about replies, mentions and likes here. <a href="<?= e(url('settings?tab=notifications')) ?>">Notification settings</a></p></div>
<?php else: ?>
    <ul class="notification-list">
        <?php foreach ($notes as $n):
            $href = url('thread/' . (int)$n['thread_id'] . ($n['post_id'] ? '?post=' . (int)$n['post_id'] : ''));
        ?>
            <li class="notification-item<?= (int)$n['is_read'] === 0 ? ' unread' : '' ?>">
                <span class="notification-icon"><i data-lucide="<?= $n['type'] === 'mention' ? 'at-sign' : ($n['type'] === 'like' ? 'heart' : 'reply') ?>"></i></span>
                <div class="notification-meta">
                    <a href="<?= e(url('profile/' . rawurlencode($n['actor_username']))) ?>"><strong><?= e($n['actor_username']) ?></strong></a>
                    <?php if ($n['type'] === 'mention'): ?>mentioned you in
                    <?php elseif ($n['type'] === 'like'): ?><?= $n['post_id'] ? 'liked your reply in' : 'liked your thread' ?>
                    <?php else: ?>replied to<?php endif; ?>
                    <a href="<?= e($href) ?>"><?= e($n['thread_title'] ?? ('thread #' . (int)$n['thread_id'])) ?></a>
                    <span class="notification-time"><?= time_tag((int)$n['created_at']) ?></span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
