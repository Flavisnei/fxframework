<?php

declare(strict_types=1);

namespace Fx\Framework\Auth;

final class Gate
{
    /** @var array<string,callable> */
    private array $abilities = [];

    public function __construct(private readonly SessionGuard $guard)
    {
    }

    public function define(string $ability, callable $callback): self
    {
        $this->abilities[$ability] = $callback;
        return $this;
    }

    public function allows(string $ability, mixed ...$arguments): bool
    {
        $user = $this->guard->user();
        return $user !== null && isset($this->abilities[$ability]) && (bool) ($this->abilities[$ability])($user, ...$arguments);
    }

    public function authorize(string $ability, mixed ...$arguments): void
    {
        if (!$this->allows($ability, ...$arguments)) {
            throw new AuthorizationException('Esta acao nao foi autorizada.');
        }
    }
}
