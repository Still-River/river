<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

class SessionManager
{
    private const STATE_KEY = 'google_oauth_state';
    private const REDIRECT_KEY = 'post_login_redirect';
    private const USER_ID_KEY = 'authenticated_user_id';

    public function generateStateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function storeState(string $state): void
    {
        $_SESSION[self::STATE_KEY] = $state;
    }

    public function validateState(?string $state): bool
    {
        if ($state === null || !isset($_SESSION[self::STATE_KEY])) {
            return false;
        }

        $isValid = hash_equals((string) $_SESSION[self::STATE_KEY], $state);
        unset($_SESSION[self::STATE_KEY]);

        return $isValid;
    }

    public function rememberPostLoginRedirect(?string $redirect): void
    {
        if ($redirect !== null && $redirect !== '') {
            $_SESSION[self::REDIRECT_KEY] = $redirect;
        }
    }

    public function popPostLoginRedirect(string $default): string
    {
        $redirect = $_SESSION[self::REDIRECT_KEY] ?? $default;
        unset($_SESSION[self::REDIRECT_KEY]);

        return $redirect;
    }

    public function setAuthenticatedUserId(int $userId): void
    {
        $_SESSION[self::USER_ID_KEY] = $userId;
    }

    public function getAuthenticatedUserId(): ?int
    {
        $userId = $_SESSION[self::USER_ID_KEY] ?? null;

        return $userId === null ? null : (int) $userId;
    }

    public function clearAuthentication(): void
    {
        unset($_SESSION[self::USER_ID_KEY]);
    }

    public function regenerate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Cannot regenerate session before it has started');
        }

        session_regenerate_id(true);
    }
}
