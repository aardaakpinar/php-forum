<?php
declare(strict_types=1);

final class Router
{
    /** @var array<string, array{controller: class-string, method: string, view: string}> */
    private static array $routes = [
        'home'          => ['controller' => HomeController::class,         'method' => 'index',    'view' => 'home'],
        'login'         => ['controller' => AuthController::class,         'method' => 'login',    'view' => 'login'],
        'register'      => ['controller' => AuthController::class,         'method' => 'register', 'view' => 'register'],
        'logout'        => ['controller' => AuthController::class,         'method' => 'logout',   'view' => 'logout'],
        'new_thread'    => ['controller' => ThreadController::class,       'method' => 'create',   'view' => 'new_thread'],
        'thread'        => ['controller' => ThreadController::class,       'method' => 'show',     'view' => 'thread'],
        'profile'       => ['controller' => ProfileController::class,      'method' => 'show',     'view' => 'profile'],
        'search'        => ['controller' => SearchController::class,       'method' => 'search',   'view' => 'search'],
        'settings'      => ['controller' => SettingsController::class,     'method' => 'index',    'view' => 'settings'],
        'notifications' => ['controller' => NotificationController::class, 'method' => 'index',    'view' => 'notifications'],
        'admin'         => ['controller' => AdminController::class,        'method' => 'index',    'view' => 'admin'],
    ];

    public static function resolveRouteName(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        $scriptDir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        $path = trim($path, '/');

        if ($path === '' || $path === 'index' || $path === 'home') {
            return 'home';
        }

        if (str_ends_with($path, '.php')) {
            $path = substr($path, 0, -4);
        }

        if ($path === 'new-thread' || $path === 'new_thread') {
            return 'new_thread';
        }

        if (preg_match('#^thread/(\d+)$#', $path, $matches)) {
            $_GET['id'] = (int)$matches[1];
            return 'thread';
        }

        if (preg_match('#^category/([a-z0-9-]+)$#', $path, $matches)) {
            $_GET['c'] = $matches[1];
            return 'home';
        }

        if (preg_match('#^profile/(.+)$#', $path, $matches)) {
            $_GET['u'] = rawurldecode($matches[1]);
            return 'profile';
        }

        return $path;
    }

    public static function dispatch(): void
    {
        $routeName = self::resolveRouteName();
        $route = self::$routes[$routeName] ?? null;

        if ($route === null) {
            self::renderNotFound();
            return;
        }

        $viewer = current_user();
        $viewerIsAdmin = $viewer && ($viewer['role'] ?? '') === 'admin';

        // Bakım modu: yalnızca adminler (ve giriş sayfası) erişebilir
        if (site_setting('maintenance_mode') && !$viewerIsAdmin && !in_array($routeName, ['login', 'logout'], true)) {
            render_maintenance();
        }

        // Yalnızca üyeler: misafirler giriş sayfasına yönlendirilir
        if (site_setting('require_login_to_read') && !$viewer && !in_array($routeName, ['login', 'register', 'logout'], true)) {
            redirect(url('login'));
        }

        $controllerClass = $route['controller'];
        $method = $route['method'];

        /** @var Controller $controller */
        $controller = new $controllerClass();
        $data = $controller->$method();

        extract($data);

        require __DIR__ . '/../views/' . $route['view'] . '.php';
    }

    private static function renderNotFound(): void
    {
        render_error(404, 'Page not found', 'The page you are looking for does not exist or has been moved.');
    }
}
