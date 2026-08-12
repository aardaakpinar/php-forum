<?php
/** @var PDO $pdo */
$__user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    verify_csrf();

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    redirect(url());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?= e($pageTitle ?? 'Forum') ?></title>
<link rel="stylesheet" href="/assets/css/main.css">
<link rel="icon" href="/assets/img/logo-rounded.png" />
<link rel="shortcut icon" href="/assets/img/logo.png" />
</head>
<body>
<header>
    <a href="<?= e(url()) ?>" class="brand">
        <span class="symbol">~</span>
    </a>
    <nav class="links">
        <a href="<?= e(url()) ?>">Threads</a>
        <a href="<?= e(url('search')) ?>">Search</a>
        <?php if ($__user): ?>
            <div class="user-menu">
                <button class="me user-toggle" type="button">
                    <?= e($__user['username']) ?>
                </button>

                <div class="user-popup">
                    <a href="<?= e(url('profile/' . rawurlencode($__user['username']))) ?>">Profile</a>
                    <?php if ($__user): ?>
                        <a href="<?= e(url('notifications')) ?>" class="notifications-link">Notifications (<?= Notification::unreadCount($__user) ?>)</a>
                    <?php endif; ?>
                    <a href="<?= e(url('settings')) ?>">Settings</a>
                    <form method="post" class="form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="logout">Logout</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <a href="<?= e(url('login')) ?>">Login</a>
            <a href="<?= e(url('register')) ?>">Register</a>
        <?php endif; ?>
    </nav>
</header>
<main class="active">
<div class="page-wrap">
