<?php $pageTitle = 'Forum - Notifications'; require __DIR__ . '/../../includes/header.php'; ?>
<div class="notifications-header">
    <h1>Notifications</h1>
    <form method="post" class="form notifications-actions">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="cta">Mark all as read</button>
    </form>
</div>

<?php if (!$notes): ?>
    <p class="empty">You have no notifications.</p>
<?php else: ?>
    <ul class="notification-list">
        <?php foreach ($notes as $n): ?>
            <li class="notification-item<?= $n['is_read'] == 0 ? ' unread' : '' ?>">
                <div class="notification-meta">
                    <span class="time"><?= e(date('d.m.Y H:i', (int)$n['created_at'])) ?></span>
                    <span class="dot">·</span>
                    <strong><?= e($n['actor_username']) ?></strong>
                    <?php if ($n['type'] === 'mention'): ?> mentioned you
                    <?php else: ?> replied in
                    <?php endif; ?>
                    <a href="<?= e(url('thread/' . (int)$n['thread_id'] . ($n['post_id'] ? '#post-' . (int)$n['post_id'] : ''))) ?>">thread #<?= (int)$n['thread_id'] ?></a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
