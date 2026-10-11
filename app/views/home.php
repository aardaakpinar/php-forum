<?php
$pageTitle = page_title($category ? $category['name'] : 'Threads');
$__nav = 'home';
require __DIR__ . '/../../includes/header.php';

$baseUrl = $category ? url('category/' . $category['slug']) : url();
$qs = static function (array $over = []) use ($sort, $filter, $defaultSort): array {
    $p = array_merge(['sort' => $sort, 'filter' => $filter ?: null], $over);
    if (($p['sort'] ?? null) === $defaultSort) {
        $p['sort'] = null; // varsayılan sıralama URL'de gösterilmez
    }
    return array_filter($p, static fn($v) => $v !== null && $v !== '');
};
$link = static fn(array $over = []) => $baseUrl . ($qs($over) ? '?' . http_build_query($qs($over)) : '');
?>

<?php if (!$user && !$category && $page === 1 && site_setting('show_welcome_intro')): ?>
<section class="intro">
    <div>
        <h1>Welcome to <?= e(site_settings()['name']) ?></h1>
        <p><?= e(site_settings()['tagline']) ?></p>
    </div>
    <div class="intro-actions">
        <?php if ($__canRegister): ?><a href="<?= e(url('register')) ?>" class="btn btn-primary">Create account</a><?php endif; ?>
        <a href="<?= e(url('login')) ?>" class="btn btn-secondary">Log in</a>
    </div>
</section>
<?php endif; ?>

<div class="layout">
    <aside class="sidebar" aria-label="Sidebar">
        <?php if (site_setting('show_categories_widget')): ?>
        <div class="widget">
            <h2 class="widget-title">Categories</h2>
            <ul class="cat-list">
                <li><a href="<?= e(url()) ?>" class="<?= !$category ? 'active' : '' ?>"><span>All threads</span><span class="count"><?= (int)$stats['threads'] ?></span></a></li>
                <?php foreach ($categories as $c): ?>
                    <li><a href="<?= e(url('category/' . $c['slug'])) ?>" class="<?= $category && (int)$category['id'] === (int)$c['id'] ? 'active' : '' ?>"><span><?= e($c['name']) ?></span><span class="count"><?= (int)$c['thread_count'] ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (site_setting('show_stats_widget')): ?>
        <div class="widget">
            <h2 class="widget-title">Forum stats</h2>
            <dl class="stat-list">
                <div><dt>Threads</dt><dd><?= compact_number((int)$stats['threads']) ?></dd></div>
                <div><dt>Replies</dt><dd><?= compact_number((int)$stats['posts']) ?></dd></div>
                <div><dt>Members</dt><dd><?= compact_number((int)$stats['members']) ?></dd></div>
            </dl>
            <?php if (!empty($stats['newest'])): ?>
                <p class="widget-note">Newest member: <a href="<?= e(url('profile/' . rawurlencode($stats['newest']['username']))) ?>"><?= e($stats['newest']['username']) ?></a></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </aside>
    <div>
        <div class="page-header">
            <div class="page-header-title">
                <h1><?= e($category ? $category['name'] : 'Threads') ?></h1>
                <?php if ($category && $category['description'] !== ''): ?>
                    <p class="lead"><?= e($category['description']) ?></p>
                <?php endif; ?>
            </div>
            
            <div class="page-header-actions">
                <div class="select-wrap sort-select">
                    <select aria-label="Sort threads" data-nav-select>
                        <option value="<?= e($link(['sort' => 'latest', 'page' => null])) ?>"<?= $sort === 'latest' ? ' selected' : '' ?>>Latest</option>
                        <option value="<?= e($link(['sort' => 'new', 'page' => null])) ?>"<?= $sort === 'new' ? ' selected' : '' ?>>New</option>
                        <option value="<?= e($link(['sort' => 'top', 'page' => null])) ?>"<?= $sort === 'top' ? ' selected' : '' ?>>Top</option>
                        <option value="<?= e($link(['sort' => 'views', 'page' => null])) ?>"<?= $sort === 'views' ? ' selected' : '' ?>>Most viewed</option>
                    </select>
                    <i data-lucide="chevron-down"></i>
                </div>
                <?php if ($user): ?>
                <div class="page-actions">
                    <a href="<?= e(url('new-thread') . ($category ? '?c=' . rawurlencode($category['slug']) : '')) ?>" class="btn btn-primary"><i data-lucide="plus"></i>New thread</a>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <div class="chips">
            <a class="chip<?= $filter === '' ? ' active' : '' ?>" href="<?= e($link(['filter' => null, 'page' => null])) ?>">All</a>
            <?php if ($user): ?>
                <a class="chip<?= $filter === 'following' ? ' active' : '' ?>" href="<?= e($link(['filter' => 'following', 'page' => null])) ?>"><i data-lucide="bell"></i>Following</a>
                <a class="chip<?= $filter === 'mine' ? ' active' : '' ?>" href="<?= e($link(['filter' => 'mine', 'page' => null])) ?>"><i data-lucide="user"></i>My threads</a>
            <?php endif; ?>
            <a class="chip<?= $filter === 'unanswered' ? ' active' : '' ?>" href="<?= e($link(['filter' => 'unanswered', 'page' => null])) ?>"><i data-lucide="circle-help"></i>Unanswered</a>
        </div>

        <?php if (!$threads): ?>
            <div class="card empty-state">
                <i data-lucide="inbox"></i>
                <h3>No threads here yet</h3>
                <p><?= $filter ? 'Nothing matches this filter.' : 'Be the first to start a conversation.' ?></p>
                <?php if ($user): ?><a class="btn btn-primary" href="<?= e(url('new-thread') . ($category ? '?c=' . rawurlencode($category['slug']) : '')) ?>">Start a thread</a><?php endif; ?>
            </div>
        <?php else: ?>
            <?php require __DIR__ . '/partials/thread_rows.php'; ?>
            <?= pagination($page, $pages, $baseUrl, $qs()) ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
