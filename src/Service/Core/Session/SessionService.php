<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Session;

use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Exception\InvalidStateException;

readonly class SessionService
{
    private const SESSION_NAME = 'session';
    private const SESSION_OPTIONS = [
        'name' => self::SESSION_NAME,
        'cookie_lifetime' => 3600,
        'cookie_secure' => false, // TODO: Change for live
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'use_only_cookies' => true,
    ];
    private const string KEY_USER = 'user';

    /**
     * @return void
     *
     * Will start a session ONLY if a session cookie is detected and no session is active.
     */
    public function resumeSession(): void
    {
        if ($this->isSessionActive()) {
            throw new InvalidStateException('Session already started.');
        }
        if (!isset($_COOKIE[self::SESSION_NAME])) {
            return;
        }
        session_start(self::SESSION_OPTIONS);
    }

    public function startSession(): void
    {
        if ($this->isSessionActive()) {
            throw new InvalidStateException('Session already started.');
        }
        session_start(self::SESSION_OPTIONS);
    }

    public function setUserData(SessionUserData $userData): void
    {
        $_SESSION[self::KEY_USER] = $userData;
    }

    public function getUserData(): ?SessionUserData
    {
        return $_SESSION[self::KEY_USER] ?? null;
    }

    public function destroySession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(self::SESSION_NAME, '', 0, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    private function isSessionActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}