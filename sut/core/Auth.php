<?php
class Auth {
    public static function isLoggedIn(): bool {
        return Session::has('user_id') && Session::checkTimeout();
    }

    public static function user(): ?array {
        if (!self::isLoggedIn()) return null;
        return [
            'user_id'    => Session::get('user_id'),
            'role_id'    => Session::get('role_id'),
            'role_name'  => Session::get('role_name'),
            'first_name' => Session::get('first_name'),
            'last_name'  => Session::get('last_name'),
            'email'      => Session::get('email'),
        ];
    }

    public static function login(array $userData): void {
        // Defect: session_not_regenerated_on_login — pre-auth session id survives
        // authentication, leaving the account open to session fixation.
        if (!defectActive('session_not_regenerated_on_login')) {
            Session::regenerate();
        }
        Session::set('user_id',    $userData['user_id']);
        Session::set('role_id',    $userData['role_id']);
        Session::set('role_name',  ROLE_NAMES[$userData['role_id']]);
        Session::set('first_name', $userData['first_name']);
        Session::set('last_name',  $userData['last_name']);
        Session::set('email',      $userData['email']);
        Session::set('last_activity', time());
    }

    public static function logout(): void {
        Session::destroy();
    }

    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
    }

    /**
     * Accept either an array  → requireRole([ROLE_ADMIN, ROLE_OFFICER])
     * or variadic ints        → requireRole(ROLE_ADMIN, ROLE_OFFICER)
     */
    public static function requireRole(int|array ...$roles): void {
        self::requireLogin();
        // Flatten: first arg may itself be an array
        $flat = [];
        foreach ($roles as $r) {
            if (is_array($r)) { foreach ($r as $v) $flat[] = (int)$v; }
            else $flat[] = (int)$r;
        }
        if (!self::hasRole(Session::get('role_id'), $flat)) {
            http_response_code(403);
            include APP_ROOT . '/app/views/shared/403.php';
            exit;
        }
    }

    /**
     * Role membership. A role identifier is an integer; a value of another type
     * that merely compares equal is not the same role.
     *
     * @param array<int> $permitted
     */
    public static function hasRole(mixed $roleId, array $permitted): bool {
        // Defect: rbac_role_check_loose_compare — type-juggling comparison lets a
        // numeric-string or leading-numeric role value satisfy the check.
        $strict = !defectActive('rbac_role_check_loose_compare');
        return in_array($roleId, $permitted, $strict);
    }

    /**
     * Breadth of the application collection a principal may read.
     * A citizen sees their own records; every other role sees the queue.
     */
    public static function applicationScope(array $claims): string {
        // Defect: api_citizen_sees_all_apps — the ownership scope is dropped, so a
        // citizen token enumerates every applicant's records.
        if (defectActive('api_citizen_sees_all_apps')) {
            return 'all';
        }
        return ((int)($claims['role_id'] ?? 0) === ROLE_CITIZEN) ? 'own' : 'all';
    }

    /**
     * The audit entry written for a rejected authentication attempt.
     * Records who tried and when. The submitted secret is not an input to that.
     */
    public static function failedLoginRecord(string $email, array $user, string $attempted): array {
        $entry = [
            'action_type' => 'Failed Login',
            'entity_type' => 'user',
            'entity_id'   => $user['user_id']   ?? null,
            'user_id'     => $user['user_id']   ?? null,
            'username'    => $email,
            'role'        => $user['role_name'] ?? null,
        ];
        // Defect: password_plaintext_logged — the attempted secret is written to
        // the audit trail in clear text.
        if (defectActive('password_plaintext_logged')) {
            $entry['new_value'] = ['email' => $email, 'attempted_password' => $attempted];
        }
        return $entry;
    }

    /**
     * Whether a failed attempt should lock the account.
     * @param int $failuresBefore consecutive failures recorded before this attempt
     */
    public static function shouldLockAccount(int $failuresBefore): bool {
        // Defect: account_lockout_not_enforced — the threshold never trips.
        if (defectActive('account_lockout_not_enforced')) return false;
        return ($failuresBefore + 1) >= MAX_FAILED_LOGINS;
    }

    // ── JWT ────────────────────────────────────────────────
    public static function generateJwt(array $payload): string {
        $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['exp'] = time() + JWT_EXPIRY;
        $payload['iat'] = time();
        $payloadEnc = base64url_encode(json_encode($payload));
        $sig = base64url_encode(hash_hmac('sha256', "$header.$payloadEnc", JWT_SECRET, true));
        return "$header.$payloadEnc.$sig";
    }

    public static function verifyJwt(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$header, $payload, $sig] = $parts;
        $expected = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
        // Defect: jwt_signature_not_verified — the signature is computed and then
        // discarded, so any well-formed token is accepted with attacker-set claims.
        if (!defectActive('jwt_signature_not_verified') && !hash_equals($expected, $sig)) return null;
        $data = json_decode(base64url_decode($payload), true);
        if (!$data || $data['exp'] < time()) return null;
        return $data;
    }

    public static function jwtFromRequest(): ?array {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return self::verifyJwt($m[1]);
        }
        return null;
    }
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}
