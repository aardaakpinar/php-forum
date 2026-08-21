<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid request.');
    }
}

function current_user(): ?array
{
    static $cached = null;
    static $resolved = false;
    if ($resolved) {
        return $cached;
    }
    $resolved = true;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $cached = User::findPublic((int)$_SESSION['user_id']);
    return $cached;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect(url('login'));
    }
    return $user;
}

function app_base_path(): string
{
    $scriptDir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return $scriptDir === '' ? '' : $scriptDir;
}

function url(string $path = ''): string
{
    $basePath = app_base_path();
    if ($path === '' || $path === '/') {
        return $basePath === '' ? '/' : $basePath . '/';
    }

    $cleanPath = '/' . ltrim($path, '/');
    return $basePath . $cleanPath;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function validate_username(string $u): ?string
{
    $u = trim($u);
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $u)) {
        return 'The username must be 3-20 characters long and can only contain letters, numbers, and underscores.';
    }
    return null;
}

function validate_email(string $email): ?string
{
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }
    return null;
}

function validate_password(string $p): ?string
{
    if (strlen($p) < 10) {
        return 'The password must be at least 10 characters long.';
    }
    if (strlen($p) > 200) {
        return 'The password is too long.';
    }
    if (!preg_match('/[A-Z]/', $p) || !preg_match('/[a-z]/', $p) || !preg_match('/[0-9]/', $p)) {
        return 'The password must contain at least one uppercase letter, one lowercase letter, and one number.';
    }
    return null;
}

function is_locked(array $u): bool
{
    return (int)$u['locked_until'] > time();
}

function str_len_safe(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

function findTitlesByIds(array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if ($ids === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = Database::get()->prepare("SELECT id, title FROM threads WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $titles = [];
    foreach ($stmt->fetchAll() as $row) {
        $titles[(int)$row['id']] = $row['title'];
    }
    return $titles;
}

function linkify_tags(string $text): string
{
    $text = preg_replace_callback(
        '/(?<![A-Za-z0-9_])@([A-Za-z0-9_]{3,20})/',
        static function (array $m): string {
            return '<a href="' . e(url('profile/' . rawurlencode($m[1]))) . '" class="mention">@' . e($m[1]) . '</a>';
        },
        $text
    );

    $threadIds = [];
    if (preg_match_all('/(?<![A-Za-z0-9_&])#(\d+)/', $text, $allMatches)) {
        $threadIds = $allMatches[1];
    }
    $threadTitles = $threadIds !== [] ? findTitlesByIds($threadIds) : [];

    $text = preg_replace_callback(
        '/(?<![A-Za-z0-9_&])#(\d+)/',
        static function (array $m) use ($threadTitles): string {
            $id = (int)$m[1];
            if (!isset($threadTitles[$id])) {
                return '<a href="' . e(url('thread/' . $id)) . '" class="mention">#' . $id . '</a>';
            }
            return '<a href="' . e(url('thread/' . $id)) . '" class="mention">' . e($threadTitles[$id]) . '</a>';
        },
        $text
    );

    return $text;
}

function render_markdown(string $text): string
{
    // Escape all input first
    $text = e($text);

    // Protect fenced code blocks by replacing them with placeholders
    $code_blocks = [];
    $text = preg_replace_callback('/```(.*?)```/s', static function ($m) use (&$code_blocks) {
        $idx = count($code_blocks);
        $code_blocks[$idx] = '<pre><code>' . $m[1] . '</code></pre>';
        return '%%CODEBLOCK' . $idx . '%%';
    }, $text);

    // Protect inline code with placeholders
    $inline_codes = [];
    $text = preg_replace_callback('/`([^`]+)`/', static function ($m) use (&$inline_codes) {
        $idx = count($inline_codes);
        $inline_codes[$idx] = '<code>' . $m[1] . '</code>';
        return '%%INLINECODE' . $idx . '%%';
    }, $text);

    // Convert mentions and #thread refs to anchors (works on escaped text)
    $text = linkify_tags($text);

    // Markdown links: [text](url) - only allow http/https
    $text = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/i', static function ($m) {
        $url = htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<a href="' . $url . '">' . $m[1] . '</a>';
    }, $text);

    // Bold **text**
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);

    // Italic *text* (avoid catching **bold**)
    $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)\*(?!\*)/s', '<em>$1</em>', $text);

    // Headings: only # - ### supported (h2-h3)
    $text = preg_replace_callback('/^(#{2,3})\s*(.+)$/m', static function ($m) {
        $level = min(3, strlen($m[1]));
        return '<h' . $level . '>' . $m[2] . '</h' . $level . '>';
    }, $text);

    // Split by two or more newlines into paragraphs. Preserve existing block tags.
    $parts = preg_split('/\n{2,}/', $text);
    $out = '';

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }

        if (preg_match('/^<(h[1-3]|pre|ul|ol|blockquote)/', $part)) {
            $out .= $part;
        } else {
            $out .= '<p>' . nl2br($part, false) . '</p>';
        }
    }

    // Restore inline code placeholders
    if (!empty($inline_codes)) {
        foreach ($inline_codes as $i => $html) {
            $out = str_replace('%%INLINECODE' . $i . '%%', $html, $out);
        }
    }

    // Restore fenced code block placeholders
    if (!empty($code_blocks)) {
        foreach ($code_blocks as $i => $html) {
            $out = str_replace('%%CODEBLOCK' . $i . '%%', $html, $out);
        }
    }

    $out = preg_replace(
        '/(<\/h[1-3]>|<\/pre>)<br\s*\/?>/i',
        '$1',
        $out
    );

    return $out;
}

const MAX_MENTIONS_PER_POST = 20;

function extract_mentioned_user_ids(string $text): array
{
    if (!preg_match_all('/(?<![A-Za-z0-9_])@([A-Za-z0-9_]{3,20})/', $text, $m)) {
        return [];
    }
    $usernames = array_slice(array_unique($m[1]), 0, MAX_MENTIONS_PER_POST);
    if (!$usernames) {
        return [];
    }
    return User::idsByUsernames($usernames);
}