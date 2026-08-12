<?php $pageTitle = 'Forum - Search'; require __DIR__ . '/../../includes/header.php'; ?>
<h1>Search</h1>
<form method="get" action="<?= e(url('search')) ?>" class="form-group line">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search threads..." required>
    <button type="submit" class="cta">Search</button>
</form>

<?php if ($q === ''): ?>
    <p class="hint">Enter a query to search threads.</p>
<?php else: ?>
    <h2>Results for "<?= e($q) ?>" (<?= count($results) ?>)</h2>
    <?php if (!$results): ?>
        <p class="empty">No results found.</p>
    <?php else: ?>
        <ul class="thread-list">
            <?php foreach ($results as $t):
                $isUnread = $user && ((int)($t['last_read_at'] ?? 0) < (int)$t['last_activity_at']);
            ?>
                <li class="thread-item<?= (int)$t['pinned'] === 1 ? ' pinned' : '' ?><?= $isUnread ? ' unread' : '' ?>">
                    <a class="thread-title" href="<?= e(url('thread/' . (int)$t['id'])) ?>">
                        <?php if ($isUnread): ?><span class="unread-dot" title="Unread"></span><?php endif; ?>
                        <?= e($t['title']) ?>
                    </a>
                    <div class="thread-meta">
                        <span><?= e($t['username']) ?></span>
                        <span class="dot">·</span>
                        <span><?= e(date('d.m.Y H:i', (int)$t['created_at'])) ?></span>
                        <span class="dot">·</span>
                        <span><?= (int)$t['reply_count'] ?> Replies</span>
                    </div>
                    <div class="excerpt"><?= e(mb_substr(strip_tags(render_markdown($t['body'])), 0, 200)) ?>...</div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
