<?php $pageTitle = 'Forum - @' . $profileUser['username']; require __DIR__ . '/../../includes/header.php'; ?>
<h1><?= e($profileUser['username']) ?>
    <?php if ($profileUser['role'] === 'admin'): ?><span class="role-badge">[admin]</span><?php endif; ?>
</h1>

<div class="profile-stats">
    <div class="stat"><span class="stat-value"><?= $threadCount ?></span><span class="stat-label">Threads</span></div>
    <div class="stat"><span class="stat-value"><?= $postCount ?></span><span class="stat-label">Replies</span></div>
    <div class="stat"><span class="stat-value"><?= e(date('d.m.Y', (int)$profileUser['created_at'])) ?></span><span class="stat-label">Joined</span></div>
</div>

<h2>Recent Threads</h2>
<?php if (!$recentThreads): ?>
    <p class="empty">No threads yet.</p>
<?php else: ?>
    <ul class="thread-list">
        <?php foreach ($recentThreads as $t):
            $isUnread = $viewer && ((int)($t['last_read_at'] ?? 0) < (int)$t['last_activity_at']);
        ?>
            <li class="thread-item<?= $isUnread ? ' unread' : '' ?>">
                <a class="thread-title" href="<?= e(url('thread/' . (int)$t['id'])) ?>">
                    <?php if ($isUnread): ?><span class="unread-dot" title="Unread"></span><?php endif; ?>
                    <?= e($t['title']) ?>
                </a>
                <div class="thread-meta">
                    <span><?= e(date('d.m.Y H:i', (int)$t['created_at'])) ?></span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
