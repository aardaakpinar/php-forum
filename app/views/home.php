<?php $pageTitle = 'Forum - Threads'; require __DIR__ . '/../../includes/header.php'; ?>
<div class="page-header">
    <h1>Threads</h1>
    <?php if ($user): ?>
        <a href="<?= e(url('new-thread')) ?>" class="cta">New Thread</a>
    <?php endif; ?>
</div>

<?php if (!$threads): ?>
    <p class="empty">
        No threads yet.
        <?php if ($user): ?><a href="<?= e(url('new-thread')) ?>">Bring up the first threads.</a><?php endif; ?>
    </p>
<?php else: ?>
    <ul class="thread-list">
        <?php foreach ($threads as $t):
            $isUnread = $user && ((int)($t['last_read_at'] ?? 0) < (int)$t['last_activity_at']);
        ?>
            <li class="thread-item<?= (int)$t['pinned'] === 1 ? ' pinned' : '' ?><?= $isUnread ? ' unread' : '' ?>">
                <a class="thread-title" href="<?= e(url('thread/' . (int)$t['id'])) ?>">
                    <?php if ($isUnread): ?><span class="unread-dot" title="Unread"></span><?php endif; ?>
                    <?php if ((int)$t['pinned'] === 1): ?><i data-lucide="pin" class="pin-icon"></i><?php endif; ?>
                    <?php if ((int)$t['locked'] === 1): ?><i data-lucide="lock" class="lock-icon"></i><?php endif; ?>
                    <?= e($t['title']) ?>
                </a>
                <div class="thread-meta">
                    <span><?= e($t['username']) ?></span>
                    <span class="dot">·</span>
                    <span><?= e(date('d.m.Y H:i', (int)$t['created_at'])) ?></span>
                    <span class="dot">·</span>
                    <span><?= (int)$t['reply_count'] ?> Replies</span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>