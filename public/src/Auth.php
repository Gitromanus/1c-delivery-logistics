<?php

/**
 * Авторизация: admin / dispatcher / driver / sales.
 * Сессия «бесконечная» (cookie ~10 лет + gc_maxlifetime).
 */
class Auth
{
    private const SESSION_LIFETIME = 315360000; // ~10 лет

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $life = self::SESSION_LIFETIME;
        ini_set('session.gc_maxlifetime', (string) $life);
        session_set_cookie_params([
            'lifetime' => $life,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        // Продлеваем cookie при каждом запросе
        if (!empty($_SESSION['user_id'])) {
            setcookie(session_name(), session_id(), [
                'expires' => time() + $life,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function user(): ?array
    {
        self::startSession();
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $pdo = Database::pdo();
            $st = $pdo->prepare(
                'SELECT id, login, name, role, vehicle_id, zone_id, is_active FROM users WHERE id = ? LIMIT 1'
            );
            $st->execute([(int) $_SESSION['user_id']]);
            $u = $st->fetch();
            if (!$u || !(int) $u['is_active']) {
                self::logout();
                return null;
            }
            $cache = $u;
            return $cache;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u ? (string) $u['role'] : null;
    }

    public static function isAdmin(): bool
    {
        return in_array(self::role(), ['admin', 'dispatcher'], true);
    }

    public static function requireLogin(string $redirect = 'login.php'): void
    {
        if (!self::check()) {
            $here = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: ' . $redirect . '?next=' . rawurlencode($here));
            exit;
        }
    }

    public static function requireAdmin(string $redirect = '../login.php'): void
    {
        self::requireLogin($redirect);
        if (!self::isAdmin()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Доступ только для администратора / диспетчера';
            exit;
        }
    }

    public static function attempt(string $login, string $password): bool
    {
        self::startSession();
        $login = trim($login);
        if ($login === '' || $password === '') {
            return false;
        }

        try {
            $pdo = Database::pdo();
            // Таблица может ещё не существовать — fallback на config
            $st = $pdo->prepare(
                'SELECT id, password_hash, is_active, role FROM users WHERE login = ? LIMIT 1'
            );
            $st->execute([$login]);
            $row = $st->fetch();
            if ($row && (int) $row['is_active'] && password_verify($password, $row['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $row['id'];
                $_SESSION['role'] = $row['role'];
                return true;
            }
        } catch (Throwable $e) {
            // fallback
        }

        // Fallback: config admin_password (логин admin)
        $configPath = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';
        if (is_file($configPath)) {
            $cfg = require $configPath;
            $cfgPass = (string) ($cfg['admin_password'] ?? '');
            if ($login === 'admin' && $cfgPass !== '' && hash_equals($cfgPass, $password)) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = 0;
                $_SESSION['role'] = 'admin';
                $_SESSION['config_admin'] = true;
                $_SESSION['user_name'] = 'admin';
                return true;
            }
        }

        return false;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }

    /** Фильтр данных рабочего стола по роли */
    public static function deskFilter(): array
    {
        $u = self::user();
        if (!$u) {
            return ['mode' => 'none'];
        }
        $role = $u['role'] ?? 'dispatcher';
        if (in_array($role, ['admin', 'dispatcher'], true) || !empty($_SESSION['config_admin'])) {
            return ['mode' => 'all'];
        }
        if ($role === 'driver') {
            return [
                'mode' => 'driver',
                'vehicle_id' => $u['vehicle_id'] !== null ? (int) $u['vehicle_id'] : 0,
            ];
        }
        if ($role === 'sales') {
            return [
                'mode' => 'sales',
                'zone_id' => $u['zone_id'] !== null ? (int) $u['zone_id'] : 0,
            ];
        }
        return ['mode' => 'all'];
    }
}
