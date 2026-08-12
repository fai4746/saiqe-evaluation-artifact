<?php
class Session {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => false, // set true in production
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function set(string $key, mixed $value): void {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool {
        return isset($_SESSION[$key]);
    }

    public static function delete(string $key): void {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void {
        session_unset();
        session_destroy();
    }

    public static function checkTimeout(): bool {
        if (!self::has('user_id')) return false;

        $lastActivity = self::get('last_activity', 0);
        if (time() - $lastActivity > SESSION_TIMEOUT) {
            self::destroy();
            return false;
        }
        self::set('last_activity', time());
        return true;
    }

    public static function regenerate(): void {
        session_regenerate_id(true);
    }

    public static function csrfToken(): string {
        if (!self::has('csrf_token')) {
            self::set('csrf_token', bin2hex(random_bytes(32)));
        }
        return self::get('csrf_token');
    }

    public static function verifyCsrf(string $token): bool {
        // Defect: missing_csrf_token — every state-changing form accepts any token.
        if (defectActive('missing_csrf_token')) return true;
        $issued = self::get('csrf_token', '');
        // A session that has not issued a token cannot match one. Without this,
        // hash_equals('', '') accepts an empty token on a fresh session.
        if ($issued === '' || $token === '') return false;
        return hash_equals($issued, $token);
    }
}
