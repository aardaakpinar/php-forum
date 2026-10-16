<?php $pageTitle = page_title('Admin'); $__nav = 'admin'; require __DIR__ . '/../../includes/header.php'; ?>
<div class="page-header"><div><h1>Admin panel</h1><p class="lead">Manage your community.</p></div><span class="badge" title="Forum version">v<?= e(ltrim(APP_VERSION, 'v')) ?></span></div>

<div class="tabs tabs-gap">
    <a class="tab<?= $tab === 'overview' ? ' active' : '' ?>" href="<?= e(url('admin')) ?>"><i data-lucide="layout-dashboard"></i>Overview</a>
    <a class="tab<?= $tab === 'categories' ? ' active' : '' ?>" href="<?= e(url('admin?tab=categories')) ?>"><i data-lucide="folder"></i>Categories</a>
    <a class="tab<?= $tab === 'members' ? ' active' : '' ?>" href="<?= e(url('admin?tab=members')) ?>"><i data-lucide="users"></i>Members</a>
    <a class="tab<?= $tab === 'settings' ? ' active' : '' ?>" href="<?= e(url('admin?tab=settings')) ?>"><i data-lucide="settings"></i>Settings</a>
</div>

<?php if ($errors): ?>
<div class="alert" role="alert"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success" role="status"><p><?= e($success) ?></p></div>
<?php endif; ?>

<?php if ($tab === 'overview'): ?>
    <div class="status-strip">
        <?php $cfg = $siteSettings; $settingsLink = static fn(string $sec) => e(url('admin?tab=settings&section=' . $sec)); ?>
        <span class="status-pill <?= $cfg['maintenance_mode'] ? 'warn' : 'on' ?>"><i data-lucide="<?= $cfg['maintenance_mode'] ? 'wrench' : 'circle-check' ?>"></i><?= $cfg['maintenance_mode'] ? 'Maintenance mode ON' : 'Forum is live' ?> · <a href="<?= $settingsLink('notices') ?>">change</a></span>
        <span class="status-pill <?= $cfg['registration_open'] ? 'on' : 'warn' ?>"><i data-lucide="user-plus"></i>Registration <?= $cfg['registration_open'] ? 'open' : 'closed' ?> · <a href="<?= $settingsLink('access') ?>">change</a></span>
        <?php if ($cfg['require_login_to_read']): ?><span class="status-pill warn"><i data-lucide="lock"></i>Members-only</span><?php endif; ?>
        <?php if ($cfg['announcement_enabled']): ?><span class="status-pill on"><i data-lucide="megaphone"></i>Announcement visible</span><?php endif; ?>
        <span class="status-pill"><i data-lucide="palette"></i>Accent: <?= e(ACCENT_PRESETS[$cfg['accent']] ?? 'Zinc') ?></span>
    </div>
    <div class="stat-grid">
        <div class="stat-card"><div class="label">Members</div><div class="value"><?= number_format($stats['members']) ?></div><div class="delta">+<?= (int)$stats['members_week'] ?> this week</div></div>
        <div class="stat-card"><div class="label">Threads</div><div class="value"><?= number_format($stats['threads']) ?></div><div class="delta">+<?= (int)$stats['threads_week'] ?> this week</div></div>
        <div class="stat-card"><div class="label">Replies</div><div class="value"><?= number_format($stats['posts']) ?></div><div class="delta">+<?= (int)$stats['posts_week'] ?> this week</div></div>
        <div class="stat-card"><div class="label">Total views</div><div class="value"><?= compact_number((int)$stats['views']) ?></div><div class="label"><?= number_format($stats['likes']) ?> likes</div></div>
    </div>

    <h2 class="section-title">System</h2>
    <div class="table-wrap"><table class="table system-info">
        <tbody>
        <?php foreach ($system as $label => $value): ?>
            <tr><th scope="row"><?= e($label) ?></th><td><?= e($value) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>

    <h2 class="section-title">Latest threads</h2>
    <?php if ($recentThreads): ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Title</th><th>Author</th><th>Category</th><th class="num">Replies</th><th class="num">Created</th></tr></thead>
            <tbody>
            <?php foreach ($recentThreads as $t): ?>
                <tr>
                    <td><a href="<?= e(url('thread/' . (int)$t['id'])) ?>"><?= e($t['title']) ?></a></td>
                    <td><?= e($t['username']) ?></td>
                    <td><?= e($t['category_name'] ?? '—') ?></td>
                    <td class="num"><?= (int)$t['reply_count'] ?></td>
                    <td class="num"><?= time_tag((int)$t['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php else: ?>
        <p class="empty">No threads yet.</p>
    <?php endif; ?>

<?php elseif ($tab === 'categories'): ?>
    <form method="post" action="<?= e(url('admin?tab=categories')) ?>" class="card cat-create">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="category_create">
        <h2>New category</h2>
        <div class="cat-edit">
            <input type="text" name="name" placeholder="Name" maxlength="60" required aria-label="Name">
            <input type="text" name="slug" placeholder="slug (optional)" maxlength="60" aria-label="Slug">
            <input type="text" name="description" placeholder="Short description" maxlength="200" aria-label="Description">
            <button type="submit" class="btn btn-primary btn-sm">Add</button>
        </div>
    </form>

    <div class="table-wrap">
        <?php foreach ($categories as $c): ?>
            <div class="cat-row">
                <form method="post" action="<?= e(url('admin?tab=categories')) ?>" class="cat-edit">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <input type="text" name="name" value="<?= e($c['name']) ?>" maxlength="60" required aria-label="Name">
                    <input type="text" name="slug" value="<?= e($c['slug']) ?>" maxlength="60" required aria-label="Slug">
                    <input type="text" name="description" value="<?= e($c['description']) ?>" maxlength="200" aria-label="Description">
                    <div class="cat-actions">
                        <span class="badge"><?= (int)$c['thread_count'] ?> threads</span>
                        <button type="submit" name="action" value="category_update" class="btn btn-secondary btn-sm">Save</button>
                        <button type="submit" name="action" value="category_delete" class="btn btn-danger btn-sm" data-confirm="Delete this category? Its threads will be moved to another category.">Delete</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($tab === 'members'): ?>
    <form method="get" action="<?= e(url('admin')) ?>" class="search-inline">
        <input type="hidden" name="tab" value="members">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by username or email" maxlength="100">
        <button type="submit" class="btn btn-secondary btn-sm">Search</button>
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Member</th><th>Role</th><th class="num">Threads</th><th class="num">Replies</th><th class="num">Joined</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($members['rows'] as $m): $self = (int)$m['id'] === (int)$user['id']; ?>
            <tr>
                <td><div class="user-cell"><?= avatar($m['username'], 'sm') ?><div><a href="<?= e(url('profile/' . rawurlencode($m['username']))) ?>"><?= e($m['username']) ?></a><small><?= e($m['email']) ?></small></div></div></td>
                <td><?= $m['role'] === 'admin' ? '<span class="badge badge-role">Admin</span>' : '<span class="badge">Member</span>' ?></td>
                <td class="num"><?= (int)$m['thread_count'] ?></td>
                <td class="num"><?= (int)$m['post_count'] ?></td>
                <td class="num"><?= time_tag((int)$m['created_at']) ?></td>
                <td>
                    <?php if (!$self): ?>
                    <div class="row-actions">
                        <form method="post" action="<?= e(url('admin?tab=members')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <input type="hidden" name="role" value="<?= $m['role'] === 'admin' ? 'member' : 'admin' ?>">
                            <button type="submit" name="action" value="member_role" class="btn btn-secondary btn-sm"><?= $m['role'] === 'admin' ? 'Remove admin' : 'Make admin' ?></button>
                        </form>
                        <?php if ($m['role'] !== 'admin'): ?>
                        <form method="post" action="<?= e(url('admin?tab=members')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <button type="submit" name="action" value="member_delete" class="btn btn-danger btn-sm" data-confirm="Delete this account and all of its content?">Delete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php else: ?><span class="muted">You</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?= pagination($members['page'], $members['pages'], url('admin'), array_filter(['tab' => 'members', 'q' => $q])) ?>
<?php else: $activeSection = $siteSchema[$section]; ?>
    <div class="settings-layout">
        <nav class="settings-nav" aria-label="Site settings sections">
            <?php foreach ($siteSchema as $id => $sec): ?>
                <a href="<?= e(url('admin?tab=settings&section=' . $id)) ?>" class="<?= $section === $id ? 'active' : '' ?>"<?= $section === $id ? ' aria-current="page"' : '' ?>><i data-lucide="<?= e($sec['icon']) ?>"></i><?= e($sec['label']) ?></a>
            <?php endforeach; ?>
        </nav>

        <form method="post" action="<?= e(url('admin?tab=settings&section=' . $section)) ?>" class="card settings-panel settings-form" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="site_settings">
            <input type="hidden" name="section" value="<?= e($section) ?>">
            <div class="settings-panel-head"><h2><?= e($activeSection['label']) ?></h2><p><?= e($activeSection['description']) ?></p></div>
            <div class="setting-list">
                <?php foreach ($activeSection['fields'] as $key => $def): ?>
                    <?= render_setting_field($key, $def, $siteSettings[$key], 'settings') ?>
                <?php endforeach; ?>
            </div>
            <div class="settings-panel-foot">
                <button type="submit" class="btn btn-primary">Save settings</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
