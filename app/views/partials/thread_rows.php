<?php
/**
 * Konu satırları.
 * @var array  $threads
 * @var ?array $user
 * @var bool   $showExcerpt   (opsiyonel) arama sonuçlarında özet göster
 * @var string $highlight     (opsiyonel) vurgulanacak terim
 */
$showExcerpt = $showExcerpt ?? false;
$highlight = $highlight ?? '';
?>
<?php $showViews = (bool)site_setting('count_views'); ?>
<div class="thread-table<?= $showViews ? '' : ' no-views' ?>">
    <div class="thread-head" aria-hidden="true">
        <span></span><span>Topic</span><span>Replies</span><?php if ($showViews): ?><span>Views</span><?php endif; ?><span>Activity</span>
    </div>
    <ul class="thread-list">
        <?php foreach ($threads as $t):
            $isUnread = !empty($user) && ((int)($t['last_read_at'] ?? 0) < (int)$t['last_activity_at']);
            $replies = (int)$t['reply_count'];
        ?>
            <li class="thread-item<?= (int)$t['pinned'] === 1 ? ' pinned' : '' ?><?= $isUnread ? ' unread' : '' ?>">
                <a href="<?= e(url('profile/' . rawurlencode($t['username']))) ?>" class="thread-avatar" tabindex="-1" aria-hidden="true"><?= avatar($t['username']) ?></a>
                <div class="thread-main">
                    <div>
                        <?php if ($isUnread): ?><span class="unread-dot" title="Unread"></span><?php endif; ?>
                        <?php if ((int)$t['pinned'] === 1): ?><i data-lucide="pin" class="pin-icon" title="Pinned"></i><?php endif; ?>
                        <?php if ((int)$t['locked'] === 1): ?><i data-lucide="lock" class="lock-icon" title="Locked"></i><?php endif; ?>
                        <a class="thread-title" href="<?= e(url('thread/' . (int)$t['id'])) ?>"><?= $highlight !== '' ? highlight_term($t['title'], $highlight) : e($t['title']) ?></a>
                    </div>
                    <div class="thread-meta">
                        <?php if (!empty($t['category_name'])): ?>
                            <a class="cat-pill" href="<?= e(url('category/' . $t['category_slug'])) ?>"><?= e($t['category_name']) ?></a>
                        <?php endif; ?>
                        <span>
                            <a href="<?= e(url('profile/' . rawurlencode($t['username']))) ?>"><?= e($t['username']) ?></a>
                            started <?= time_tag((int)$t['created_at']) ?>
                        </span>
                    </div>
                    <?php if ($showExcerpt): ?>
                        <div class="excerpt"><?= $highlight !== '' ? highlight_term(plain_excerpt($t['body'], 180), $highlight) : e(plain_excerpt($t['body'], 180)) ?></div>
                    <?php endif; ?>
                </div>
                <div class="thread-stat<?= $replies >= 10 ? ' hot' : '' ?>">
                    <strong><?= compact_number($replies) ?></strong>
                    <span class="sr-only">replies</span>
                </div>
                <?php if ($showViews): ?>
                <div class="thread-stat thread-views">
                    <strong><?= compact_number((int)$t['views']) ?></strong>
                    <span class="sr-only">views</span>
                </div>
                <?php endif; ?>
                <div class="thread-activity">
                    <?= time_tag((int)$t['last_activity_at']) ?>
                    <a href="<?= e(url('profile/' . rawurlencode($t['last_username']))) ?>"><?= e($t['last_username']) ?></a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
