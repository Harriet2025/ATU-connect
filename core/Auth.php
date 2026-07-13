<?php
namespace Core;

use Models\User;

// =========================================================
// AUTH — login, logout, remember-me, current-user helpers
// =========================================================
class Auth
{
    public static function attempt(string $identifier, string $password): ?array
    {
        $user = User::findByIdentifierOrEmail($identifier);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        if ((int)$user['is_active'] === 0) {
            return null;
        }

        self::login($user);
        return $user;
    }

    public static function login(array $user): void
    {
        Session::set('user_id', $user['user_id']);
        Session::set('role', $user['role_name']);
        session_regenerate_id(true);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::has('user_id');
    }

    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    public static function role(): ?string
    {
        return Session::get('role');
    }

    public static function hasRole(array $roles): bool
    {
        return in_array(self::role(), $roles, true);
    }
}
