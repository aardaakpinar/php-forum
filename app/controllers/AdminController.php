<?php
declare(strict_types=1);

/**
 * AdminController
 * Yönetim paneli: genel bakış, kategori yönetimi, üye yönetimi.
 */
final class AdminController extends Controller
{
    public function index(): array
    {
        $user = $this->requireLogin();
        if (!$this->isAdmin($user)) {
            $this->forbidden('You do not have permission to access this page.');
        }

        $tab = (string)($_GET['tab'] ?? 'overview');
        if (!in_array($tab, ['overview', 'categories', 'members', 'settings'], true)) {
            $tab = 'overview';
        }

        $errors = [];
        $success = null;

        $siteSchema = site_settings_schema();
        $section = (string)($_GET['section'] ?? 'general');
        if (!isset($siteSchema[$section])) {
            $section = 'general';
        }

        if ($this->isPost()) {
            $this->verifyCsrf();
            $action = $this->inputAction();

            switch ($action) {
                case 'category_create':
                case 'category_update':
                    $tab = 'categories';
                    [$errors, $success] = $this->saveCategory($action === 'category_update');
                    break;
                case 'category_delete':
                    $tab = 'categories';
                    [$errors, $success] = $this->deleteCategory();
                    break;
                case 'member_role':
                    $tab = 'members';
                    [$errors, $success] = $this->changeRole($user);
                    break;
                case 'member_delete':
                    $tab = 'members';
                    [$errors, $success] = $this->deleteMember($user);
                    break;
                case 'site_settings':
                    $tab = 'settings';
                    $posted = (string)($_POST['section'] ?? '');
                    if (isset($siteSchema[$posted])) {
                        $section = $posted;
                        [$errors, $success] = $this->saveSiteSettings($section, $siteSchema[$section]['fields']);
                    } else {
                        $errors = ['Unknown settings section.'];
                    }
                    break;
            }
        }

        $data = [
            'user'    => $user,
            'tab'     => $tab,
            'errors'  => $errors,
            'success' => $success,
        ];

        if ($tab === 'overview') {
            $data['stats'] = $this->stats();
            $data['system'] = $this->systemInfo();
            $data['siteSettings'] = site_settings();
            $data['recentThreads'] = Thread::paginate(['sort' => 'new', 'per_page' => 6])['rows'];
        } elseif ($tab === 'categories') {
            $data['categories'] = Category::all();
        } elseif ($tab === 'members') {
            $q = trim((string)($_GET['q'] ?? ''));
            $data['q'] = $q;
            $data['members'] = User::paginate((int)($_GET['page'] ?? 1), 20, $q);
        } else {
            $data['siteSettings'] = site_settings();
            $data['siteSchema'] = $siteSchema;
            $data['section'] = $section;
        }

        return $data;
    }

    /**
     * Sürüm ve çalışma ortamı bilgisi (yalnızca admin panelinde gösterilir).
     *
     * @return array<string,string>
     */
    private function systemInfo(): array
    {
        $dbVersion = '—';
        try {
            $dbVersion = (string)Database::get()->query('SELECT VERSION()')->fetchColumn();
        } catch (PDOException $e) {
            error_log('Admin system info: ' . $e->getMessage());
        }

        $uploadDir = BASE_DIR . '/assets/uploads';

        return [
            'Forum version'    => 'v' . ltrim(APP_VERSION, 'v'),
            'Database schema'  => 'v' . forum_schema_version(Database::get()),
            'PHP'              => PHP_VERSION,
            'Database server'  => $dbVersion,
            'Web server'       => (string)($_SERVER['SERVER_SOFTWARE'] ?? '—'),
            'Uploads folder'   => is_dir($uploadDir) && is_writable($uploadDir) ? 'Writable' : 'Not writable',
        ];
    }

    private function stats(): array
    {
        $pdo = Database::get();
        $since = time() - 7 * 86400;
        $count = static function (string $sql, array $p = []) use ($pdo): int {
            $st = $pdo->prepare($sql);
            $st->execute($p);
            return (int)$st->fetchColumn();
        };

        return [
            'members'      => User::countAll(),
            'threads'      => Thread::countAll(),
            'posts'        => Post::countAll(),
            'likes'        => $count('SELECT COUNT(*) FROM likes'),
            'members_week' => $count('SELECT COUNT(*) FROM users WHERE created_at >= ?', [$since]),
            'threads_week' => $count('SELECT COUNT(*) FROM threads WHERE created_at >= ?', [$since]),
            'posts_week'   => $count('SELECT COUNT(*) FROM posts WHERE created_at >= ?', [$since]),
            'views'        => $count('SELECT COALESCE(SUM(views), 0) FROM threads'),
        ];
    }

    /** @return array{0: array<int,string>, 1: ?string} */
    private function saveCategory(bool $isUpdate): array
    {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $slug = slugify((string)($_POST['slug'] ?? '') ?: $name);

        if ($name === '' || str_len_safe($name) > 60) {
            return [['Category name must be between 1 and 60 characters.'], null];
        }
        if (str_len_safe($desc) > 200) {
            return [['Description must be 200 characters or fewer.'], null];
        }
        if ($slug === '' || strlen($slug) > 60) {
            return [['Please provide a valid URL slug.'], null];
        }
        if (Category::slugExists($slug, $isUpdate ? $id : 0)) {
            return [['Another category already uses this slug.'], null];
        }

        if ($isUpdate) {
            if (!Category::find($id)) {
                return [['Category not found.'], null];
            }
            Category::update($id, $name, $slug, $desc);
            return [[], 'Category updated.'];
        }

        Category::create($name, $slug, $desc);
        return [[], 'Category created.'];
    }

    /** @return array{0: array<int,string>, 1: ?string} */
    private function deleteCategory(): array
    {
        $id = (int)($_POST['id'] ?? 0);
        $category = Category::find($id);
        if (!$category) {
            return [['Category not found.'], null];
        }

        $others = array_values(array_filter(Category::all(), static fn($c) => (int)$c['id'] !== $id));
        if (!$others) {
            return [['You cannot delete the last remaining category.'], null];
        }

        // Konular ilk kalan kategoriye taşınır
        Category::delete($id, (int)$others[0]['id']);
        return [[], 'Category deleted. Its threads were moved to "' . $others[0]['name'] . '".'];
    }

    /** @return array{0: array<int,string>, 1: ?string} */
    private function changeRole(array $admin): array
    {
        $id = (int)($_POST['id'] ?? 0);
        $role = (string)($_POST['role'] ?? '');
        $target = User::findFullById($id);

        if (!$target || !in_array($role, ['admin', 'member'], true)) {
            return [['Invalid request.'], null];
        }
        if ($id === (int)$admin['id']) {
            return [['You cannot change your own role.'], null];
        }

        User::setRole($id, $role);
        return [[], $target['username'] . ' is now ' . ($role === 'admin' ? 'an admin' : 'a member') . '.'];
    }

    /** @return array{0: array<int,string>, 1: ?string} */
    private function deleteMember(array $admin): array
    {
        $id = (int)($_POST['id'] ?? 0);
        $target = User::findFullById($id);

        if (!$target) {
            return [['User not found.'], null];
        }
        if ($id === (int)$admin['id']) {
            return [['You cannot delete your own account here. Use Settings instead.'], null];
        }
        if ($target['role'] === 'admin') {
            return [['Remove admin rights before deleting this account.'], null];
        }

        User::delete($id);
        return [[], 'Account "' . $target['username'] . '" and all of its content were deleted.'];
    }

    /**
     * Tek bir ayar bölümünü kaydeder (yalnızca o bölümün alanları güncellenir).
     *
     * @param array<string,array> $fields
     * @return array{0: array<int,string>, 1: ?string}
     */
    private function saveSiteSettings(string $section, array $fields): array
    {
        $errors = [];
        $input = is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [];
        $values = settings_collect($fields, $input, $errors);

        $current = site_settings();
        if (isset($fields['logo'])) {
            [$logo, $logoError] = $this->handleLogo((string)$current['logo']);
            if ($logoError !== null) {
                $errors[] = $logoError;
            } else {
                $values['logo'] = $logo;
            }
        }
        if (isset($fields['favicon'])) {
            [$favicon, $faviconError] = $this->handleFavicon((string)($current['favicon'] ?? ''));
            if ($faviconError !== null) {
                $errors[] = $faviconError;
            } else {
                $values['favicon'] = $favicon;
            }
        }

        if ($errors) {
            // Başka bir alan hata verdiyse bu istekte yüklenen dosyaları yetim bırakma
            foreach (['logo', 'favicon'] as $k) {
                if (isset($values[$k]) && $values[$k] !== ($current[$k] ?? '') && $values[$k] !== '') {
                    $orphan = BASE_DIR . $values[$k];
                    if (is_file($orphan)) {
                        unlink($orphan);
                    }
                }
            }
            return [$errors, null];
        }

        $stmt = Database::get()->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        foreach ($values as $key => $value) {
            $stmt->execute([$key, $value]);
        }

        // Önbelleği tip dönüşümlü yeni değerlerle güncelle
        $typed = [];
        foreach ($values as $key => $value) {
            $typed[$key] = settings_cast($fields[$key], $value);
        }
        site_settings($typed);

        // Değişen logonun eski dosyasını temizle
        if (
            isset($values['logo'])
            && $current['logo'] !== $values['logo']
            && preg_match('#^/assets/uploads/[a-f0-9]{32}\.(?:png|jpg|webp|gif)$#', (string)$current['logo'])
        ) {
            $oldLogo = BASE_DIR . $current['logo'];
            if (is_file($oldLogo)) {
                unlink($oldLogo);
            }
        }

        // Değişen faviconun eski dosyasını temizle
        if (
            isset($values['favicon'])
            && ($current['favicon'] ?? '') !== $values['favicon']
            && preg_match('#^/assets/uploads/[a-f0-9]{32}\.(?:ico|png|jpg|webp|gif|svg)$#', (string)($current['favicon'] ?? ''))
        ) {
            $oldFavicon = BASE_DIR . $current['favicon'];
            if (is_file($oldFavicon)) {
                unlink($oldFavicon);
            }
        }

        $label = site_settings_schema()[$section]['label'];
        if ($section === 'notices' && !empty($values['maintenance_mode']) && $values['maintenance_mode'] === '1') {
            return [[], $label . ' settings saved. Maintenance mode is ON: only admins can use the forum.'];
        }
        return [[], $label . ' settings saved.'];
    }

    /**
     * Logo yükleme / kaldırma.
     *
     * @return array{0: string, 1: ?string} [yeni logo yolu, hata]
     */
    private function handleLogo(string $currentLogo): array
    {
        $file = $_FILES['site_logo'] ?? null;

        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return [$currentLogo, 'The logo upload failed. Please try again.'];
            }
            if (!is_uploaded_file((string)($file['tmp_name'] ?? '')) || (int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
                return [$currentLogo, 'The logo must be a valid image no larger than 2 MB.'];
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file((string)$file['tmp_name']);
            $extensions = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];
            if (!isset($extensions[$mime])) {
                return [$currentLogo, 'Logo must be a PNG, JPEG, WebP, or GIF image.'];
            }

            $uploadDir = BASE_DIR . '/assets/uploads';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                return [$currentLogo, 'The logo upload directory could not be created.'];
            }
            if (!is_writable($uploadDir)) {
                return [$currentLogo, 'The logo upload directory is not writable by the server.'];
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
            if (!move_uploaded_file((string)$file['tmp_name'], $uploadDir . '/' . $filename)) {
                return [$currentLogo, 'The uploaded logo could not be saved.'];
            }
            return ['/assets/uploads/' . $filename, null];
        }

        if (isset($_POST['remove_logo'])) {
            return ['', null];
        }

        return [$currentLogo, null];
    }

    /**
     * Favicon yükleme / kaldırma.
     *
     * @return array{0: string, 1: ?string} [yeni favicon yolu, hata]
     */
    private function handleFavicon(string $currentFavicon): array
    {
        $file = $_FILES['site_favicon'] ?? null;

        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return [$currentFavicon, 'The favicon upload failed. Please try again.'];
            }
            if (!is_uploaded_file((string)($file['tmp_name'] ?? '')) || (int)($file['size'] ?? 0) > 1 * 1024 * 1024) {
                return [$currentFavicon, 'The favicon must be a valid icon/image no larger than 1 MB.'];
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file((string)$file['tmp_name']);
            $origName = strtolower((string)($file['name'] ?? ''));

            $extensions = [
                'image/x-icon'             => 'ico',
                'image/vnd.microsoft.icon' => 'ico',
                'image/ico'                => 'ico',
                'image/icon'               => 'ico',
                'text/ico'                 => 'ico',
                'application/ico'          => 'ico',
                'image/png'                => 'png',
                'image/jpeg'               => 'jpg',
                'image/webp'               => 'webp',
                'image/gif'                => 'gif',
                'image/svg+xml'            => 'svg',
            ];

            $ext = $extensions[$mime] ?? null;
            if ($ext === null && ($mime === 'application/octet-stream' || $mime === 'image/x-ico') && str_ends_with($origName, '.ico')) {
                $bytes = file_get_contents((string)$file['tmp_name'], false, null, 0, 4);
                if ($bytes === "\x00\x00\x01\x00") {
                    $ext = 'ico';
                }
            }

            if ($ext === null) {
                return [$currentFavicon, 'Favicon must be an ICO, PNG, SVG, WebP, or GIF image.'];
            }

            if ($ext === 'svg') {
                $svgContent = file_get_contents((string)$file['tmp_name']);
                if (
                    $svgContent === false
                    || preg_match('#<\s*(script|foreignObject|iframe|object|embed|animate|set)\b|<!ENTITY|javascript:|data:text/html|\bon[a-z]+\s*=#i', $svgContent)
                ) {
                    return [$currentFavicon, 'SVG favicon contains disallowed scripts or event handlers.'];
                }
            }

            $uploadDir = BASE_DIR . '/assets/uploads';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                return [$currentFavicon, 'The favicon upload directory could not be created.'];
            }
            if (!is_writable($uploadDir)) {
                return [$currentFavicon, 'The favicon upload directory is not writable by the server.'];
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $ext;
            if (!move_uploaded_file((string)$file['tmp_name'], $uploadDir . '/' . $filename)) {
                return [$currentFavicon, 'The uploaded favicon could not be saved.'];
            }
            return ['/assets/uploads/' . $filename, null];
        }

        if (isset($_POST['remove_favicon'])) {
            return ['', null];
        }

        return [$currentFavicon, null];
    }
}
