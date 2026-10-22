<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Session;

use Closure;
use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Exception\InvalidStateException;

class SessionService
{
    public const string SESSION_NAME = 'session';
    private const SESSION_OPTIONS = [
        'name' => self::SESSION_NAME,
        'cookie_lifetime' => 24 * 60 * 60,
        'cookie_secure' => false, // TODO: Change for live
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'use_only_cookies' => true,
    ];
    private const string KEY_USER = 'user';
    private const string KEY_QUICK_TAGS_ENABLED = 'quickTagsEnabled';

    private ?Closure $cookieEmitter = null;

    /** Injects a cookie emitter for environments where response headers cannot be observed. */
    public function setCookieEmitter(Closure $cookieEmitter): void
    {
        $this->cookieEmitter = $cookieEmitter;
    }

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
        if (session_start(self::SESSION_OPTIONS)) {
            $this->refreshSessionCookie();
        }
    }

    public function discardStaleSessionCookie(): void
    {
        unset($_COOKIE[self::SESSION_NAME]);
        if ($this->headersSent()) {
            return;
        }

        $cookieParams = session_get_cookie_params();
        $cookieOptions = [
            'expires' => time() - 3600,
            'path' => $cookieParams['path'],
            'secure' => self::SESSION_OPTIONS['cookie_secure'],
            'httponly' => self::SESSION_OPTIONS['cookie_httponly'],
            'samesite' => self::SESSION_OPTIONS['cookie_samesite'],
        ];
        if ($cookieParams['domain'] !== '') {
            $cookieOptions['domain'] = $cookieParams['domain'];
        }
        $this->emitCookie(self::SESSION_NAME, '', $cookieOptions);
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

    public function setQuickTagsEnabled(bool $enabled): void
    {
        $_SESSION[self::KEY_QUICK_TAGS_ENABLED] = $enabled;
    }

    public function getQuickTagsEnabled(): bool
    {
        return ($_SESSION[self::KEY_QUICK_TAGS_ENABLED] ?? false) === true;
    }

    public function destroySession(): void
    {
        $_SESSION = [];
        if (!$this->isSessionActive()) {
            return;
        }
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

    private function refreshSessionCookie(): void
    {
        if ($this->headersSent()) {
            return;
        }

        $cookieParams = session_get_cookie_params();
        $cookieOptions = [
            'expires' => time() + $cookieParams['lifetime'],
            'path' => $cookieParams['path'],
            'secure' => $cookieParams['secure'],
            'httponly' => $cookieParams['httponly'],
            'samesite' => $cookieParams['samesite'],
        ];
        if ($cookieParams['domain'] !== '') {
            $cookieOptions['domain'] = $cookieParams['domain'];
        }

        $this->emitCookie(self::SESSION_NAME, session_id(), $cookieOptions);
    }

    protected function headersSent(): bool
    {
        return headers_sent();
    }

    /** @param array<string, int|string|bool> $options */
    private function emitCookie(string $name, string $value, array $options): void
    {
        if ($this->cookieEmitter !== null) {
            ($this->cookieEmitter)($name, $value, $options);
            return;
        }

        setcookie($name, $value, $options);
    }
}
