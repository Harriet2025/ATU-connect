<?php
namespace Core;

// =========================================================
// SESSION — secure session bootstrap + helpers
// =========================================================
class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $cfg = APP_CONFIG['session'];

        session_name($cfg['name']);
        session_set_cookie_params([
            'lifetime' => $cfg['lifetime_minutes'] * 60,
            'path'     => '/',
            'secure'   => $cfg['cookie_secure'],
            'httponly' => $cfg['cookie_httponly'],
            'samesite' => $cfg['cookie_samesite'],
        ]);
        session_start();

        // idle timeout
        $timeout = $cfg['lifetime_minutes'] * 60;
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            self::destroy();
            session_start();
        }
        $_SESSION['last_activity'] = time();
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function csrfToken(): string
    {
        if (!self::has('csrf_token')) {
            self::set('csrf_token', bin2hex(random_bytes(32)));
        }
        return self::get('csrf_token');
    }

    public static function verifyCsrf(?string $token): bool
    {
        return $token !== null && hash_equals(self::get('csrf_token', ''), $token);
    }
}
