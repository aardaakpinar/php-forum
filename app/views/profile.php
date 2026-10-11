<?php $pageTitle = page_title('@' . $profileUser['username']); require __DIR__ . '/../../includes/header.php';
$isSelf = $viewer && (int)$viewer['id'] === (int)$profileUser['id'];
$pUrl = url('profile/' . rawurlencode($profileUser['username']));
?>
<section class="card profile-card">
    <?= avatar($profileUser['username'], 'xl') ?>
    <div class="profile-info">
        <h1><?= e($profileUser['username']) ?> <?= role_badge($profileUser['role']) ?></h1>
        <?php if (!empty($profileUser['bio'])): ?>
            <p class="profile-bio"><?= nl2br(e($profileUser['bio']), false) ?></p>
        <?php elseif ($isSelf): ?>
            <p class="profile-bio empty-bio">You haven't written a bio yet. <a href="<?= e(url('settings')) ?>">Add one</a></p>
        <?php endif; ?>
        <?php if (!empty($profileUser['location']) || !empty($profileUser['website'])): ?>
        <div class="profile-meta">
            <?php if (!empty($profileUser['location'])): ?><span><i data-lucide="map-pin"></i><?= e($profileUser['location']) ?></span><?php endif; ?>
            <?php if (!empty($profileUser['website'])): ?><a href="<?= e($profileUser['website']) ?>" rel="nofollow noopener noreferrer ugc" target="_blank"><i data-lucide="link"></i><?= e(preg_replace('#^https?://(www\\.)?#i', '', rtrim($profileUser['website'], '/'))) ?></a><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="profile-stats">
            <div class="stat"><span class="stat-value"><?= (int)$threadCount ?></span><span class="stat-label">Threads</span></div>
            <div class="stat"><span class="stat-value"><?= (int)$postCount ?></span><span class="stat-label">Replies</span></div>
            <div class="stat"><span class="stat-value"><?= e(date('M j, Y', (int)$profileUser['created_at'])) ?></span><span class="stat-label">Joined</span></div>
        </div>
    </div>
    <?php if ($isSelf): ?>
        <a href="<?= e(url('settings')) ?>" class="btn btn-secondary btn-sm"><i data-lucide="settings"></i>Edit profile</a>
    <?php endif; ?>
</section>

<div class="tabs tabs-gap">
    <a class="tab<?= $tab === 'threads' ? ' active' : '' ?>" href="<?= e($pUrl) ?>">Threads <span class="tab-count"><?= (int)$threadCount ?></span></a>
    <?php if ($showReplies): ?><a class="tab<?= $tab === 'replies' ? ' active' : '' ?>" href="<?= e($pUrl . '?tab=replies') ?>">Replies <span class="tab-count"><?= (int)$postCount ?></span></a><?php endif; ?>
</div>


<?php if ($tab === 'threads'): ?>
    <?php if (!$threads['rows']): ?>
        <div class="card empty-state"><i data-lucide="inbox"></i><h3>No threads yet</h3><p>This user hasn't started any threads.</p></div>
    <?php else: ?>
        <?php $threadsList = $threads['rows']; $user = $viewer; $threads_backup = $threads; $threads = $threadsList; require __DIR__ . '/partials/thread_rows.php'; $threads = $threads_backup; ?>
        <?= pagination($threads['page'], $threads['pages'], $pUrl) ?>
    <?php endif; ?>
<?php else: ?>
    <?php if (!$replies): ?>
        <div class="card empty-state"><i data-lucide="messages-square"></i><h3>No replies yet</h3><p>This user hasn't replied to any threads.</p></div>
    <?php else: ?>
        <ul class="reply-list">
            <?php foreach ($replies as $r): ?>
                <li class="reply-item">
                    <div class="reply-on">Replied in <a href="<?= e(url('thread/' . (int)$r['thread_id'] . '?post=' . (int)$r['id'])) ?>"><?= e($r['thread_title']) ?></a> · <?= time_tag((int)$r['created_at']) ?></div>
                    <div class="excerpt"><?= e(plain_excerpt($r['body'], 220)) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
