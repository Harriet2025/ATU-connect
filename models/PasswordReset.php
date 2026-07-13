<?php

require_once __DIR__ . '/Model.php';

class PasswordReset extends Model
{
    public static function create(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        self::db()->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
        )->execute([$userId, $tokenHash, $expiresAt]);

        return $token;
    }

    public static function findValid(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);
        $stmt = self::db()->prepare(
            'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function markUsed(int $id): void
    {
        self::db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([$id]);
    }
}
