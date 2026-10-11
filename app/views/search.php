<?php $pageTitle = page_title($q !== '' ? 'Search: ' . $q : 'Search'); $__nav = 'search'; require __DIR__ . '/../../includes/header.php'; ?>
<div class="page-header"><div><h1>Search</h1><p class="lead">Find threads by title, content or replies.</p></div></div>

<form method="get" action="<?= e(url('search')) ?>" class="search-form">
    <div class="search-input">
        <i data-lucide="search"></i>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search threads…" maxlength="100" required autofocus>
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
</form>

<?php if ($q === ''): ?>
<?php else: ?>
    <p class="result-meta"><strong><?= (int)$total ?></strong> <?= $total === 1 ? 'result' : 'results' ?> for “<?= e($q) ?>”</p>
    <?php if (!$results): ?>
        <div class="card empty-state">
            <i data-lucide="search"></i>
            <h3>No results found</h3>
            <p>Try different keywords or check your spelling.</p>
        </div>
    <?php else: ?>
        <?php $threads = $results; $showExcerpt = true; $highlight = $q; require __DIR__ . '/partials/thread_rows.php'; ?>
        <?= pagination($page, $pages, url('search'), ['q' => $q]) ?>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
