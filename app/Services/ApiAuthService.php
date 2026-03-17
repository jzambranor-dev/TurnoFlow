<?php

declare(strict_types=1);

namespace App\Services;

use Database;

class ApiAuthService
{
    private const RATE_LIMIT = 60;          // requests per window
    private const RATE_WINDOW_SECONDS = 60; // window size

    /**
     * Generate a new API token. Returns the plaintext token (only shown once).
     */
    public static function createToken(int $userId, string $nombre, array $permisos, ?string $expiraEn = null): string
    {
        $pdo = Database::getConnection();
        $plainToken = 'tf_' . bin2hex(random_bytes(32)); // 67 chars: tf_ + 64 hex
        $hash = hash('sha256', $plainToken);
        $prefix = substr($plainToken, 0, 8); // tf_XXXXX for display

        $stmt = $pdo->prepare("
            INSERT INTO api_tokens (user_id, nombre, token_hash, token_prefix, permisos, expira_en)
            VALUES (:uid, :nombre, :hash, :prefix, :permisos, :expira)
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':nombre' => $nombre,
            ':hash' => $hash,
            ':prefix' => $prefix,
            ':permisos' => '{' . implode(',', $permisos) . '}',
            ':expira' => $expiraEn,
        ]);

        return $plainToken;
    }

    /**
     * Authenticate a request via Bearer token.
     * Returns token record with user info, or null if invalid.
     */
    public static function authenticate(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return null;
        }

        $plainToken = trim($m[1]);
        $hash = hash('sha256', $plainToken);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT t.*, u.nombre as user_nombre, u.apellido as user_apellido,
                   u.email as user_email, r.nombre as user_rol
            FROM api_tokens t
            JOIN users u ON u.id = t.user_id
            JOIN roles r ON r.id = u.rol_id
            WHERE t.token_hash = :hash
              AND t.activo = true
              AND u.activo = true
        ");
        $stmt->execute([':hash' => $hash]);
        $token = $stmt->fetch();

        if (!$token) {
            return null;
        }

        // Check expiry
        if ($token['expira_en'] && strtotime($token['expira_en']) < time()) {
            return null;
        }

        // Update last used
        $upd = $pdo->prepare("UPDATE api_tokens SET ultimo_uso = NOW() WHERE id = :id");
        $upd->execute([':id' => $token['id']]);

        return $token;
    }

    /**
     * Check if token has a specific permission.
     */
    public static function hasPermission(array $token, string $permission): bool
    {
        // Parse PostgreSQL array: {reports.view,reports.export}
        $permisos = $token['permisos'] ?? '{}';
        if (is_string($permisos)) {
            $permisos = trim($permisos, '{}');
            $permisos = $permisos === '' ? [] : explode(',', $permisos);
        }

        return in_array('*', $permisos, true) || in_array($permission, $permisos, true);
    }

    /**
     * Check rate limit. Returns true if within limit, false if exceeded.
     */
    public static function checkRateLimit(int $tokenId, string $endpoint): bool
    {
        $pdo = Database::getConnection();

        // Count requests in current window
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cnt FROM api_rate_log
            WHERE token_id = :tid
              AND created_at > NOW() - INTERVAL '" . self::RATE_WINDOW_SECONDS . " seconds'
        ");
        $stmt->execute([':tid' => $tokenId]);
        $count = (int)$stmt->fetch()['cnt'];

        if ($count >= self::RATE_LIMIT) {
            return false;
        }

        // Log this request
        $ins = $pdo->prepare("INSERT INTO api_rate_log (token_id, endpoint) VALUES (:tid, :ep)");
        $ins->execute([':tid' => $tokenId, ':ep' => $endpoint]);

        return true;
    }

    /**
     * Clean old rate log entries (call periodically).
     */
    public static function cleanRateLog(): void
    {
        $pdo = Database::getConnection();
        $pdo->exec("DELETE FROM api_rate_log WHERE created_at < NOW() - INTERVAL '1 hour'");
    }

    /**
     * Send a JSON error response and exit.
     */
    public static function jsonError(int $httpCode, string $message): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send a JSON success response and exit.
     */
    public static function jsonSuccess(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Revoke a token by ID.
     */
    public static function revokeToken(int $tokenId): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE api_tokens SET activo = false WHERE id = :id");
        $stmt->execute([':id' => $tokenId]);
    }

    /**
     * List tokens for a user (or all if admin).
     */
    public static function listTokens(?int $userId = null): array
    {
        $pdo = Database::getConnection();
        if ($userId) {
            $stmt = $pdo->prepare("
                SELECT t.id, t.nombre, t.token_prefix, t.permisos, t.ultimo_uso, t.expira_en, t.activo, t.created_at,
                       u.nombre || ' ' || u.apellido as usuario
                FROM api_tokens t
                JOIN users u ON u.id = t.user_id
                WHERE t.user_id = :uid
                ORDER BY t.created_at DESC
            ");
            $stmt->execute([':uid' => $userId]);
        } else {
            $stmt = $pdo->query("
                SELECT t.id, t.nombre, t.token_prefix, t.permisos, t.ultimo_uso, t.expira_en, t.activo, t.created_at,
                       u.nombre || ' ' || u.apellido as usuario
                FROM api_tokens t
                JOIN users u ON u.id = t.user_id
                ORDER BY t.created_at DESC
            ");
        }
        return $stmt->fetchAll();
    }
}
