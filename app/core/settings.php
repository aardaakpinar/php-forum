<?php
declare(strict_types=1);

/**
 * Ayar sistemi.
 *
 * Hem site (yönetici) ayarları hem de kullanıcı tercihleri bu dosyadaki şemalardan
 * türetilir: tip dönüşümü, doğrulama ve form alanı çizimi tek yerde yapılır.
 * Yeni bir ayar eklemek için ilgili şemaya bir satır eklemek yeterlidir.
 *
 * Alan tipleri: bool, int, text, textarea, select, choice, swatch, logo
 */

const ACCENT_PRESETS = [
    'zinc'    => 'Zinc',
    'blue'    => 'Blue',
    'violet'  => 'Violet',
    'emerald' => 'Emerald',
    'amber'   => 'Amber',
    'rose'    => 'Rose',
    'cyan'    => 'Cyan',
];

const SORT_LABELS = [
    'latest' => 'Latest activity',
    'new'    => 'Newest threads',
    'top'    => 'Most replies',
    'views'  => 'Most viewed',
];

// ---------------------------------------------------------------------
// Şemalar
// ---------------------------------------------------------------------

/** Site ayarları: bölüm => [label, icon, description, fields]. */
function site_settings_schema(): array
{
    static $schema = null;
    if ($schema !== null) {
        return $schema;
    }

    $schema = [
        'general' => [
            'label' => 'General',
            'icon' => 'globe',
            'description' => 'Your forum\'s name, tagline and logo.',
            'fields' => [
                'name' => ['type' => 'text', 'label' => 'Site name', 'default' => APP_NAME, 'maxlen' => 60, 'required' => true],
                'tagline' => ['type' => 'text', 'label' => 'Tagline', 'help' => 'Shown to visitors on the home page.', 'default' => APP_TAGLINE, 'maxlen' => 160],
                'logo' => ['type' => 'logo', 'label' => 'Logo', 'default' => ''],
            ],
        ],
        'appearance' => [
            'label' => 'Appearance',
            'icon' => 'palette',
            'description' => 'Default look of the forum. Members can override colour, density and date style in their own settings.',
            'fields' => [
                'accent' => ['type' => 'swatch', 'label' => 'Accent colour', 'help' => 'Used for buttons, links and highlights.', 'default' => 'zinc', 'options' => ACCENT_PRESETS],
                'density' => ['type' => 'choice', 'label' => 'Density', 'help' => 'Compact tightens spacing in lists and posts.', 'default' => 'comfortable', 'options' => ['comfortable' => 'Comfortable', 'compact' => 'Compact']],
                'date_format' => ['type' => 'choice', 'label' => 'Date style', 'help' => 'Relative: "5m ago". Exact: "Oct 11, 2026 · 14:30".', 'default' => 'relative', 'options' => ['relative' => 'Relative', 'absolute' => 'Exact']],
                'show_welcome_intro' => ['type' => 'bool', 'label' => 'Welcome banner for guests', 'help' => 'Show the intro card with sign-up buttons to logged-out visitors.', 'default' => true],
                'show_categories_widget' => ['type' => 'bool', 'label' => 'Categories sidebar widget', 'default' => true],
                'show_stats_widget' => ['type' => 'bool', 'label' => 'Forum stats sidebar widget', 'default' => true],
            ],
        ],
        'content' => [
            'label' => 'Content & posting',
            'icon' => 'message-square-text',
            'description' => 'Limits and rules for threads and replies.',
            'fields' => [
                'threads_per_page' => ['type' => 'int', 'label' => 'Threads per page', 'default' => 20, 'min' => 5, 'max' => 100],
                'posts_per_page' => ['type' => 'int', 'label' => 'Replies per page', 'default' => 15, 'min' => 5, 'max' => 100],
                'default_sort' => ['type' => 'select', 'label' => 'Default thread sorting', 'default' => 'latest', 'options' => SORT_LABELS],
                'max_title_length' => ['type' => 'int', 'label' => 'Max thread title length', 'help' => 'Characters.', 'default' => 150, 'min' => 20, 'max' => 255],
                'max_post_length' => ['type' => 'int', 'label' => 'Max post length', 'help' => 'Characters, for thread bodies and replies.', 'default' => 10000, 'min' => 500, 'max' => 15000],
                'post_cooldown_seconds' => ['type' => 'int', 'label' => 'Posting cooldown', 'help' => 'Seconds a member must wait between posts. 0 disables it. Admins are exempt.', 'default' => 0, 'min' => 0, 'max' => 600],
                'edit_window_minutes' => ['type' => 'int', 'label' => 'Edit window', 'help' => 'Minutes after posting during which authors can edit. 0 means no limit. Admins are exempt.', 'default' => 0, 'min' => 0, 'max' => 10080],
                'allow_likes' => ['type' => 'bool', 'label' => 'Enable likes', 'default' => true],
                'count_views' => ['type' => 'bool', 'label' => 'Count and show thread views', 'default' => true],
            ],
        ],
        'access' => [
            'label' => 'Registration & access',
            'icon' => 'user-plus',
            'description' => 'Who can join and how passwords are validated.',
            'fields' => [
                'registration_open' => ['type' => 'bool', 'label' => 'Allow new registrations', 'default' => true],
                'registration_closed_message' => ['type' => 'text', 'label' => 'Message when registration is closed', 'default' => 'Registration is currently closed.', 'maxlen' => 200],
                'require_login_to_read' => ['type' => 'bool', 'label' => 'Members-only forum', 'help' => 'Visitors must log in before they can read anything.', 'default' => false],
                'min_password_length' => ['type' => 'int', 'label' => 'Minimum password length', 'help' => 'Applies to new accounts and password changes.', 'default' => 10, 'min' => 8, 'max' => 64],
                'password_complexity' => ['type' => 'bool', 'label' => 'Require upper, lower case and a number', 'default' => true],
                'reserved_usernames' => ['type' => 'textarea', 'label' => 'Reserved usernames', 'help' => 'Comma-separated. These names cannot be registered.', 'default' => 'admin,administrator,moderator,mod,staff,support,system,root', 'maxlen' => 500, 'rows' => 2],
            ],
        ],
        'security' => [
            'label' => 'Security',
            'icon' => 'shield-check',
            'description' => 'Brute-force protection for the login form.',
            'fields' => [
                'login_max_attempts' => ['type' => 'int', 'label' => 'Failed logins before lockout', 'default' => 5, 'min' => 3, 'max' => 20],
                'lockout_minutes' => ['type' => 'int', 'label' => 'Lockout duration', 'help' => 'Minutes an account stays locked after too many failed logins.', 'default' => 15, 'min' => 1, 'max' => 1440],
            ],
        ],
        'notices' => [
            'label' => 'Notices & maintenance',
            'icon' => 'megaphone',
            'description' => 'Site-wide announcement banner and maintenance mode.',
            'fields' => [
                'announcement_enabled' => ['type' => 'bool', 'label' => 'Show announcement banner', 'default' => false],
                'announcement_text' => ['type' => 'text', 'label' => 'Announcement text', 'default' => '', 'maxlen' => 255],
                'announcement_type' => ['type' => 'choice', 'label' => 'Banner style', 'default' => 'info', 'options' => ['info' => 'Info', 'success' => 'Success', 'warning' => 'Warning']],
                'maintenance_mode' => ['type' => 'bool', 'label' => 'Maintenance mode', 'help' => 'Only admins can use the forum. Everyone else sees the message below.', 'default' => false],
                'maintenance_message' => ['type' => 'text', 'label' => 'Maintenance message', 'default' => 'We are doing some maintenance. Please check back soon.', 'maxlen' => 255],
            ],
        ],
    ];

    return $schema;
}

/** Kullanıcı tercihleri: sekme => [label, icon, description, fields]. */
function user_settings_schema(): array
{
    static $schema = null;
    if ($schema !== null) {
        return $schema;
    }

    $inherit = ['' => 'Site default'];

    $schema = [
        'appearance' => [
            'label' => 'Appearance',
            'icon' => 'palette',
            'description' => 'Choose how the forum looks for you. These settings apply only to your account.',
            'fields' => [
                'accent' => ['type' => 'swatch', 'label' => 'Accent colour', 'default' => '', 'options' => $inherit + ACCENT_PRESETS],
                'density' => ['type' => 'choice', 'label' => 'Density', 'default' => '', 'options' => $inherit + ['comfortable' => 'Comfortable', 'compact' => 'Compact']],
                'date_format' => ['type' => 'choice', 'label' => 'Date style', 'default' => '', 'options' => $inherit + ['relative' => 'Relative', 'absolute' => 'Exact']],
            ],
        ],
        'browsing' => [
            'label' => 'Browsing',
            'icon' => 'layout-list',
            'description' => 'Control how lists are sorted and paginated, and what happens when you post.',
            'fields' => [
                'default_sort' => ['type' => 'select', 'label' => 'Default thread sorting', 'default' => '', 'options' => $inherit + SORT_LABELS],
                'threads_per_page' => ['type' => 'select', 'label' => 'Threads per page', 'default' => '', 'options' => $inherit + ['10' => '10', '20' => '20', '30' => '30', '50' => '50']],
                'posts_per_page' => ['type' => 'select', 'label' => 'Replies per page', 'default' => '', 'options' => $inherit + ['10' => '10', '15' => '15', '25' => '25', '40' => '40']],
                'auto_follow' => ['type' => 'bool', 'label' => 'Auto-follow threads I post in', 'help' => 'Automatically follow threads you create or reply to, so you get notified of new replies.', 'default' => false],
            ],
        ],
        'notifications' => [
            'label' => 'Notifications',
            'icon' => 'bell',
            'description' => 'Choose which events create a notification for you.',
            'fields' => [
                'notify_replies' => ['type' => 'bool', 'label' => 'Replies in threads I follow', 'default' => true],
                'notify_mentions' => ['type' => 'bool', 'label' => 'Mentions of my @username', 'default' => true],
                'notify_likes' => ['type' => 'bool', 'label' => 'Likes on my threads and replies', 'default' => true],
            ],
        ],
        'privacy' => [
            'label' => 'Privacy',
            'icon' => 'lock',
            'description' => 'Decide who can see your profile and what it shows.',
            'fields' => [
                'profile_visibility' => ['type' => 'choice', 'label' => 'Profile visibility', 'help' => 'Members only hides your profile from logged-out visitors.', 'default' => 'public', 'options' => ['public' => 'Public', 'members' => 'Members only']],
                'show_replies' => ['type' => 'bool', 'label' => 'Show my replies on my profile', 'default' => true],
                'show_location' => ['type' => 'bool', 'label' => 'Show my location on my profile', 'default' => true],
            ],
        ],
    ];

    return $schema;
}

/** Bölümlere bakmadan tüm alanlar: key => tanım. */
function settings_flat_fields(array $schema): array
{
    $flat = [];
    foreach ($schema as $section) {
        foreach ($section['fields'] as $key => $def) {
            $flat[$key] = $def;
        }
    }
    return $flat;
}

// ---------------------------------------------------------------------
// Tip dönüşümü ve doğrulama
// ---------------------------------------------------------------------

/** Veritabanında string saklanan değeri alan tipine çevirir. */
function settings_cast(array $def, ?string $raw): bool|int|string
{
    $default = $def['default'];
    if ($raw === null) {
        return $default;
    }

    switch ($def['type']) {
        case 'bool':
            return $raw === '1';
        case 'int':
            if (!is_numeric($raw)) {
                return $default;
            }
            return max((int)$def['min'], min((int)$def['max'], (int)$raw));
        case 'select':
        case 'choice':
        case 'swatch':
            return array_key_exists($raw, $def['options']) ? $raw : $default;
        default:
            return $raw;
    }
}

/** Kaydedilecek string değerlerini üretir; hataları $errors'a ekler. */
function settings_collect(array $fields, array $input, array &$errors): array
{
    $values = [];
    foreach ($fields as $key => $def) {
        if ($def['type'] === 'logo') {
            continue;
        }
        $raw = $input[$key] ?? null;
        $label = $def['label'];

        switch ($def['type']) {
            case 'bool':
                $values[$key] = ($raw === '1') ? '1' : '0';
                break;

            case 'int':
                if (!is_string($raw) || !preg_match('/^\d{1,6}$/', trim($raw))) {
                    $errors[] = $label . ' must be a whole number.';
                    break;
                }
                $n = (int)trim($raw);
                if ($n < $def['min'] || $n > $def['max']) {
                    $errors[] = $label . ' must be between ' . $def['min'] . ' and ' . $def['max'] . '.';
                    break;
                }
                $values[$key] = (string)$n;
                break;

            case 'select':
            case 'choice':
            case 'swatch':
                if (!is_string($raw) || !array_key_exists($raw, $def['options'])) {
                    $errors[] = $label . ': please choose one of the available options.';
                    break;
                }
                $values[$key] = $raw;
                break;

            default: // text, textarea
                $text = is_string($raw) ? trim($raw) : '';
                if (!empty($def['required']) && $text === '') {
                    $errors[] = $label . ' is required.';
                    break;
                }
                if (isset($def['maxlen']) && str_len_safe($text) > $def['maxlen']) {
                    $errors[] = $label . ' must be ' . $def['maxlen'] . ' characters or fewer.';
                    break;
                }
                if (!empty($def['email']) && $text !== '' && !filter_var($text, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = $label . ' must be a valid email address.';
                    break;
                }
                if ($def['type'] === 'textarea') {
                    $text = preg_replace('/\s*[\r\n]+\s*/', ',', $text) ?? $text;
                }
                $values[$key] = $text;
        }
    }
    return $values;
}

// ---------------------------------------------------------------------
// Okuma yardımcıları
// ---------------------------------------------------------------------

/** Tek bir site ayarı (tip dönüşümü yapılmış). */
function site_setting(string $key): bool|int|string|null
{
    return site_settings()[$key] ?? null;
}

/** Kullanıcının tüm tercihleri (varsayılanlarla birleştirilmiş, tip dönüşümlü). */
function user_prefs_for(int $uid, bool $refresh = false): array
{
    static $cache = [];
    if ($refresh) {
        unset($cache[$uid]);
    }
    if (!isset($cache[$uid])) {
        $raw = User::rawPrefs($uid);
        $prefs = [];
        foreach (settings_flat_fields(user_settings_schema()) as $key => $def) {
            $prefs[$key] = settings_cast($def, $raw[$key] ?? null);
        }
        $cache[$uid] = $prefs;
    }
    return $cache[$uid];
}

/**
 * Geçerli ayar: giriş yapmış kullanıcının tercihi doluysa o, değilse site ayarı.
 * (accent, density, date_format, default_sort, threads_per_page, posts_per_page)
 */
function effective_setting(string $key): bool|int|string|null
{
    $site = site_setting($key);
    $user = current_user();
    if ($user) {
        $pref = user_prefs_for((int)$user['id'])[$key] ?? '';
        if ($pref !== '' && $pref !== null) {
            return is_int($site) ? (int)$pref : $pref;
        }
    }
    return $site;
}

// ---------------------------------------------------------------------
// Alan çizimi
// ---------------------------------------------------------------------

function render_setting_field(string $key, array $def, bool|int|string $value, string $ns = 'settings'): string
{
    $id = $ns . '-' . $key;
    $name = $ns . '[' . $key . ']';
    $label = e($def['label']);
    $help = isset($def['help']) ? '<span class="setting-help">' . e($def['help']) . '</span>' : '';
    $type = $def['type'];

    $text = static fn(string $forId = '') => '<span class="setting-text">'
        . ($forId !== '' ? '<label class="setting-title" for="' . e($forId) . '">' . $label . '</label>' : '<span class="setting-title" id="' . e($id) . '-label">' . $label . '</span>')
        . $help . '</span>';

    switch ($type) {
        case 'bool':
            return '<label class="setting-row" for="' . e($id) . '">'
                . '<span class="setting-text"><span class="setting-title">' . $label . '</span>' . $help . '</span>'
                . '<input type="checkbox" class="switch" id="' . e($id) . '" name="' . e($name) . '" value="1"' . ($value ? ' checked' : '') . '>'
                . '</label>';

        case 'int':
            return '<div class="setting-row">' . $text($id)
                . '<input type="number" class="input-sm" id="' . e($id) . '" name="' . e($name) . '" value="' . (int)$value . '" min="' . (int)$def['min'] . '" max="' . (int)$def['max'] . '" inputmode="numeric" required>'
                . '</div>';

        case 'select':
            $opts = '';
            foreach ($def['options'] as $v => $l) {
                $opts .= '<option value="' . e((string)$v) . '"' . ((string)$v === (string)$value ? ' selected' : '') . '>' . e($l) . '</option>';
            }
            return '<div class="setting-row">' . $text($id)
                . '<div class="select-wrap select-sm"><select id="' . e($id) . '" name="' . e($name) . '">' . $opts . '</select><i data-lucide="chevron-down"></i></div>'
                . '</div>';

        case 'choice':
            $opts = '';
            foreach ($def['options'] as $v => $l) {
                $opts .= '<label><input type="radio" name="' . e($name) . '" value="' . e((string)$v) . '"' . ((string)$v === (string)$value ? ' checked' : '') . '><span>' . e($l) . '</span></label>';
            }
            return '<div class="setting-row">' . $text()
                . '<span class="segmented" role="radiogroup" aria-labelledby="' . e($id) . '-label">' . $opts . '</span>'
                . '</div>';

        case 'swatch':
            $opts = '';
            foreach ($def['options'] as $v => $l) {
                $cls = $v === '' ? 'swatch-default' : 'swatch-' . $v;
                $opts .= '<label class="swatch ' . e($cls) . '" title="' . e($l) . '"><input type="radio" name="' . e($name) . '" value="' . e((string)$v) . '"' . ((string)$v === (string)$value ? ' checked' : '') . '><span><span class="sr-only">' . e($l) . '</span></span></label>';
            }
            return '<div class="setting-row">' . $text()
                . '<span class="swatches" role="radiogroup" aria-labelledby="' . e($id) . '-label">' . $opts . '</span>'
                . '</div>';

        case 'textarea':
            return '<div class="setting-row stack">' . $text($id)
                . '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="' . (int)($def['rows'] ?? 3) . '" maxlength="' . (int)($def['maxlen'] ?? 500) . '">' . e((string)$value) . '</textarea>'
                . '</div>';

        case 'logo':
            $preview = '';
            if ((string)$value !== '') {
                $preview = '<div class="site-logo-preview"><img src="' . e(url((string)$value)) . '" alt="Current site logo" width="48" height="48">'
                    . '<label class="check-inline"><input type="checkbox" name="remove_logo" value="1"> Use default logo</label></div>';
            }
            return '<div class="setting-row stack">' . $text('site-logo')
                . '<span class="setting-help">PNG, JPEG, WebP or GIF, up to 2 MB.</span>'
                . '<input id="site-logo" type="file" name="site_logo" accept="image/png,image/jpeg,image/webp,image/gif">'
                . $preview . '</div>';

        default: // text
            return '<div class="setting-row stack">' . $text($id)
                . '<input type="text" id="' . e($id) . '" name="' . e($name) . '" value="' . e((string)$value) . '"' . (isset($def['maxlen']) ? ' maxlength="' . (int)$def['maxlen'] . '"' : '') . (!empty($def['required']) ? ' required' : '') . '>'
                . '</div>';
    }
}
