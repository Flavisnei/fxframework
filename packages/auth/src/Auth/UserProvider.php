<?php

declare(strict_types=1);

namespace Fx\Framework\Auth;

interface UserProvider
{
    public function retrieveById(string|int $identifier): ?Authenticatable;
    /** @param array<string,mixed> $credentials */ public function retrieveByCredentials(array $credentials): ?Authenticatable;
    /** @param array<string,mixed> $credentials */ public function validateCredentials(Authenticatable $user, array $credentials): bool;
}
