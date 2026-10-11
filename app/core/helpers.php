<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Tüm site ayarları (tip dönüşümü yapılmış). Şema: settings.php > site_settings_schema().
 * $updatedSettings verilirse önbellek bu değerlerle güncellenir.
 */
function site_settings(?array $updatedSettings = null): array
{
    static $settings = null;
    if ($updatedSettings !== null) {
        $settings = array_merge($settings ?? [], $updatedSettings);
        return $settings;
    }
    if ($settings !== null) {
        return $settings;
    }

    $fields = settings_flat_fields(site_settings_schema());
    $raw = [];
    $stmt = Database::get()->query('SELECT setting_key, setting_value FROM site_settings');
    foreach ($stmt->fetchAll() as $row) {
        $raw[$row['setting_key']] = $row['setting_value'];
    }

    $settings = [];
    foreach ($fields as $key => $def) {
        $settings[$key] = settings_cast($def, $raw[$key] ?? null);
    }

    return $settings;
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
        render_error(403, 'Invalid request', 'Your session token is missing or expired. Go back, refresh the page and try again.');
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
    $min = (int)site_setting('min_password_length');
    if (strlen($p) < $min) {
        return 'The password must be at least ' . $min . ' characters long.';
    }
    if (strlen($p) > 200) {
        return 'The password is too long.';
    }
    if (site_setting('password_complexity')
        && (!preg_match('/[A-Z]/', $p) || !preg_match('/[a-z]/', $p) || !preg_match('/[0-9]/', $p))) {
        return 'The password must contain at least one uppercase letter, one lowercase letter, and one number.';
    }
    return null;
}

/** Şifre kuralını kullanıcıya gösterilecek kısa metin olarak döndürür. */
function password_hint(): string
{
    $min = (int)site_setting('min_password_length');
    return 'At least ' . $min . ' characters'
        . (site_setting('password_complexity') ? ' with an uppercase letter, a lowercase letter and a number.' : '.');
}

function is_reserved_username(string $username): bool
{
    $list = preg_split('/[\s,]+/', strtolower((string)site_setting('reserved_usernames'))) ?: [];
    return in_array(strtolower($username), array_filter($list), true);
}

function validate_location(string $location): ?string
{
    return str_len_safe($location) > 60 ? 'Location must be 60 characters or fewer.' : null;
}

function validate_website(string $website): ?string
{
    if ($website === '') {
        return null;
    }
    if (strlen($website) > 100 || !preg_match('#^https?://[^\s<>"\']+$#i', $website) || !filter_var($website, FILTER_VALIDATE_URL)) {
        return 'Website must be a valid http:// or https:// address (100 characters max).';
    }
    return null;
}

/** İçerik (konu/cevap) düzenleme yetkisi: sahip (düzenleme süresi içinde) veya admin. */
function can_edit_content(?array $user, int $ownerId, int $createdAt): bool
{
    if (!$user) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }
    if ((int)$user['id'] !== $ownerId) {
        return false;
    }
    $window = (int)site_setting('edit_window_minutes');
    return $window === 0 || (time() - $createdAt) <= $window * 60;
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

/**
 * Satır içi markdown (girdi zaten e() ile escape edilmiş olmalıdır).
 */
function render_inline(string $text): string
{
    // @kullanici ve #123 bağlantıları
    $text = linkify_tags($text);

    // [metin](url) — yalnızca http/https
    $text = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/i', static function ($m) {
        $url = htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        return '<a href="' . $url . '" rel="nofollow noopener noreferrer ugc" target="_blank">' . $m[1] . '</a>';
    }, $text);

    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)\*(?!\*)/s', '<em>$1</em>', $text);
    $text = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $text);

    return $text;
}

/**
 * Blok seviyesinde markdown: paragraf, başlık, liste, alıntı, ayraç.
 * Girdi escape edilmiştir; kod blokları yer tutucu ile korunur.
 */
function render_blocks(string $text): string
{
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $out = '';
    $para = [];
    $i = 0;
    $n = count($lines);

    $flush = static function () use (&$para, &$out): void {
        if ($para) {
            $out .= '<p>' . implode('<br>', array_map('render_inline', $para)) . '</p>';
            $para = [];
        }
    };

    while ($i < $n) {
        $line = $lines[$i];
        $trim = trim($line);

        if ($trim === '') {
            $flush();
            $i++;
            continue;
        }

        // Kod bloğu yer tutucusu (tek başına satır)
        if (preg_match('/^%%CODEBLOCK\d+%%$/', $trim)) {
            $flush();
            $out .= $trim;
            $i++;
            continue;
        }

        // Yatay çizgi
        if (preg_match('/^(-{3,}|\*{3,})$/', $trim)) {
            $flush();
            $out .= '<hr>';
            $i++;
            continue;
        }

        // Başlık (## / ###)
        if (preg_match('/^(#{2,3})\s+(.+)$/', $trim, $m)) {
            $flush();
            $level = strlen($m[1]);
            $out .= '<h' . $level . '>' . render_inline($m[2]) . '</h' . $level . '>';
            $i++;
            continue;
        }

        // Alıntı (> ...) — e() sonrası ">" => "&gt;"
        if (preg_match('/^&gt;\s?/', $trim)) {
            $flush();
            $buf = [];
            while ($i < $n && preg_match('/^\s*&gt;\s?(.*)$/', $lines[$i], $m)) {
                $buf[] = $m[1];
                $i++;
            }
            $out .= '<blockquote>' . render_blocks(implode("\n", $buf)) . '</blockquote>';
            continue;
        }

        // Madde işaretli liste
        if (preg_match('/^[-*]\s+/', $trim)) {
            $flush();
            $out .= '<ul>';
            while ($i < $n && preg_match('/^\s*[-*]\s+(.*)$/', $lines[$i], $m)) {
                $out .= '<li>' . render_inline($m[1]) . '</li>';
                $i++;
            }
            $out .= '</ul>';
            continue;
        }

        // Numaralı liste
        if (preg_match('/^\d{1,3}[.)]\s+/', $trim)) {
            $flush();
            $out .= '<ol>';
            while ($i < $n && preg_match('/^\s*\d{1,3}[.)]\s+(.*)$/', $lines[$i], $m)) {
                $out .= '<li>' . render_inline($m[1]) . '</li>';
                $i++;
            }
            $out .= '</ol>';
            continue;
        }

        $para[] = $line;
        $i++;
    }

    $flush();
    return $out;
}

function render_markdown(string $text): string
{
    // Önce her şeyi escape et
    $text = e($text);

    // ``` çitli kod blokları
    $code_blocks = [];
    $text = preg_replace_callback('/```(.*?)```/s', static function ($m) use (&$code_blocks) {
        $idx = count($code_blocks);
        $code = $m[1];
        $lang = '';
        if (preg_match('/^([A-Za-z0-9_+#-]{1,15})\r?\n/', $code, $lm)) {
            $lang = strtolower($lm[1]);
            $code = substr($code, strlen($lm[0]));
        } else {
            $code = preg_replace('/^\r?\n/', '', $code);
        }
        $code = rtrim($code);
        $attr = $lang !== '' ? ' data-lang="' . $lang . '"' : '';
        $code_blocks[$idx] = '<pre' . $attr . '><code>' . $code . '</code></pre>';
        return "\n\n%%CODEBLOCK" . $idx . "%%\n\n";
    }, $text);

    // `satır içi kod`
    $inline_codes = [];
    $text = preg_replace_callback('/`([^`\n]+)`/', static function ($m) use (&$inline_codes) {
        $idx = count($inline_codes);
        $inline_codes[$idx] = '<code>' . $m[1] . '</code>';
        return '%%INLINECODE' . $idx . '%%';
    }, $text);

    $out = render_blocks($text);

    foreach ($inline_codes as $i => $html) {
        $out = str_replace('%%INLINECODE' . $i . '%%', $html, $out);
    }
    foreach ($code_blocks as $i => $html) {
        $out = str_replace('%%CODEBLOCK' . $i . '%%', $html, $out);
    }

    return $out;
}

/** Markdown'sız düz özet (arama sonuçları, bildirimler için). */
function plain_excerpt(string $markdown, int $length = 200): string
{
    $t = preg_replace('/```.*?```/s', ' ', $markdown);
    $t = preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)\s]+\)/i', '$1', $t);
    $t = preg_replace('/[*_~`>#]+/', '', $t);
    $t = trim(preg_replace('/\s+/', ' ', $t));
    if (function_exists('mb_strlen') && mb_strlen($t, 'UTF-8') > $length) {
        return rtrim(mb_substr($t, 0, $length, 'UTF-8')) . '…';
    }
    return $t;
}

/** Düz metni escape eder ve arama terimini <mark> ile vurgular. */
function highlight_term(string $text, string $term): string
{
    $term = trim($term);
    if ($term === '') {
        return e($text);
    }
    $parts = preg_split('/(' . preg_quote($term, '/') . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return e($text);
    }
    $html = '';
    foreach ($parts as $idx => $part) {
        $html .= ($idx % 2 === 1) ? '<mark>' . e($part) . '</mark>' : e($part);
    }
    return $html;
}

// ---------------------------------------------------------------------
// Görünüm yardımcıları
// ---------------------------------------------------------------------

/** "5 min ago" gibi göreli zaman. */
function time_ago(int $ts): string
{
    $diff = time() - $ts;
    if ($diff < 45) {
        return 'just now';
    }
    if ($diff < 3600) {
        return max(1, (int)round($diff / 60)) . 'm ago';
    }
    if ($diff < 86400) {
        return (int)round($diff / 3600) . 'h ago';
    }
    if ($diff < 86400 * 7) {
        return (int)round($diff / 86400) . 'd ago';
    }
    return date(date('Y', $ts) === date('Y') ? 'M j' : 'M j, Y', $ts);
}

/** <time> etiketi: görünen göreli zaman, title'da tam tarih. */
function time_tag(int $ts, bool $absolute = false): string
{
    $full = date('M j, Y · H:i', $ts);
    $absolute = $absolute || effective_setting('date_format') === 'absolute';
    $label = $absolute ? $full : time_ago($ts);
    return '<time datetime="' . e(date('c', $ts)) . '" title="' . e($full) . '">' . e($label) . '</time>';
}

/** 1200 => 1.2k */
function compact_number(int $n): string
{
    if ($n >= 1000000) {
        return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . 'M';
    }
    if ($n >= 1000) {
        return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'k';
    }
    return (string)$n;
}

/** Kullanıcı adından harf avatar (renk sınıfı kullanıcı adından türetilir). */
function avatar(string $username, string $size = 'md'): string
{
    $initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($username, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($username, 0, 1));
    $tone = crc32(strtolower($username)) % 8;
    return '<span class="avatar avatar-' . e($size) . ' tone-' . $tone . '" aria-hidden="true">' . e($initial) . '</span>';
}

function role_badge(?string $role): string
{
    if ($role === 'admin') {
        return '<span class="badge badge-role">Admin</span>';
    }
    return '';
}

/**
 * Sayfalama bağlantıları. $params mevcut sorgu parametreleridir (page hariç).
 */
function pagination(int $page, int $pages, string $baseUrl, array $params = []): string
{
    if ($pages <= 1) {
        return '';
    }

    $link = static function (int $p, string $label, string $cls = '', string $aria = '') use ($baseUrl, $params): string {
        $q = $params;
        if ($p > 1) {
            $q['page'] = $p;
        } else {
            unset($q['page']);
        }
        $href = $baseUrl . ($q ? '?' . http_build_query($q) : '');
        return '<a class="page-link ' . $cls . '" href="' . e($href) . '"' . ($aria ? ' aria-label="' . e($aria) . '"' : '') . '>' . $label . '</a>';
    };

    $items = [];
    $window = 1;
    for ($p = 1; $p <= $pages; $p++) {
        if ($p === 1 || $p === $pages || abs($p - $page) <= $window) {
            $items[] = $p;
        } elseif (end($items) !== '…') {
            $items[] = '…';
        }
    }

    $html = '<nav class="pagination" aria-label="Pagination">';
    if ($page > 1) {
        $html .= $link($page - 1, '<i data-lucide="chevron-left"></i>', 'page-prev', 'Previous page');
    }
    foreach ($items as $it) {
        if ($it === '…') {
            $html .= '<span class="page-gap">…</span>';
        } elseif ($it === $page) {
            $html .= '<span class="page-link current" aria-current="page">' . $it . '</span>';
        } else {
            $html .= $link($it, (string)$it);
        }
    }
    if ($page < $pages) {
        $html .= $link($page + 1, '<i data-lucide="chevron-right"></i>', 'page-next', 'Next page');
    }
    return $html . '</nav>';
}

/** Hata sayfası (404/403/400...). */
function render_error(int $code, string $title, string $message): never
{
    http_response_code($code);
    $pageTitle = $title . ' · ' . site_settings()['name'];
    require __DIR__ . '/../../includes/header.php';
    ?>
    <div class="error-page">
        <p class="error-code"><?= (int)$code ?></p>
        <h1><?= e($title) ?></h1>
        <p class="muted"><?= e($message) ?></p>
        <a class="btn btn-secondary" href="<?= e(url()) ?>">Back to forum</a>
    </div>
    <?php
    require __DIR__ . '/../../includes/footer.php';
    exit;
}

/** Bakım modu sayfası (503). */
function render_maintenance(): never
{
    http_response_code(503);
    header('Retry-After: 3600');
    $pageTitle = 'Maintenance · ' . site_settings()['name'];
    require __DIR__ . '/../../includes/header.php';
    ?>
    <div class="error-page">
        <p class="error-code"><i data-lucide="wrench" class="error-icon"></i></p>
        <h1>Down for maintenance</h1>
        <p class="muted"><?= e((string)site_setting('maintenance_message')) ?></p>
        <?php if (!current_user()): ?><a class="btn btn-secondary" href="<?= e(url('login')) ?>">Admin log in</a><?php endif; ?>
    </div>
    <?php
    require __DIR__ . '/../../includes/footer.php';
    exit;
}

function validate_bio(string $bio): ?string
{
    if (str_len_safe($bio) > 300) {
        return 'Bio must be 300 characters or fewer.';
    }
    return null;
}

function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string)$s, '-');
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
function page_title(string $title = ''): string
{
    $name = site_settings()['name'];
    return $title === '' ? $name : $title . ' · ' . $name;
}
