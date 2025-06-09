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
        'sid_length' => 64,
        'sid_bits_per_character' => 6,
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

    private function isSessionActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}