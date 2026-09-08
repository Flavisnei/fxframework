<?php

declare(strict_types=1);

namespace Fx\Framework\Auth;

final class SessionGuard
{
    private ?Authenticatable $user = null;

    public function __construct(private readonly UserProvider $provider, private readonly string $sessionKey = '_fx_auth')
    {
    }

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) { return $this->user; }
        $this->startSession();
        $identifier = $_SESSION[$this->sessionKey] ?? null;
        return $identifier === null ? null : $this->user = $this->provider->retrieveById($identifier);
    }

    public function check(): bool { return $this->user() !== null; }
    public function guest(): bool { return !$this->check(); }

    /** @param array<string,mixed> $credentials */
    public function attempt(array $credentials): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);
        if ($user === null || !$this->provider->validateCredentials($user, $credentials)) { return false; }
        $this->login($user);
        return true;
    }

    public function login(Authenticatable $user): void
    {
        $this->startSession();
        session_regenerate_id(true);
        $_SESSION[$this->sessionKey] = $user->getAuthIdentifier();
        $this->user = $user;
    }

    public function logout(): void
    {
        $this->startSession();
        unset($_SESSION[$this->sessionKey]);
        $this->user = null;
        session_regenerate_id(true);
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
            throw new \RuntimeException('Nao foi possivel iniciar a sessao de autenticacao.');
        }
    }
}
