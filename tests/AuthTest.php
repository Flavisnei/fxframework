<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Auth\Authenticatable;
use Fx\Framework\Auth\AuthorizationException;
use Fx\Framework\Auth\Gate;
use Fx\Framework\Auth\SessionGuard;
use Fx\Framework\Auth\UserProvider;
use PHPUnit\Framework\TestCase;

final class TestUser implements Authenticatable
{
    public function __construct(public int $id, public string $role = 'user') {}
    public function getAuthIdentifier(): string|int { return $this->id; }
}

final class MemoryUserProvider implements UserProvider
{
    public function retrieveById(string|int $identifier): ?Authenticatable { return (int) $identifier === 1 ? new TestUser(1, 'admin') : null; }
    public function retrieveByCredentials(array $credentials): ?Authenticatable { return ($credentials['email'] ?? '') === 'admin@example.com' ? new TestUser(1, 'admin') : null; }
    public function validateCredentials(Authenticatable $user, array $credentials): bool { return ($credentials['password'] ?? '') === 'secret'; }
}

final class AuthTest extends TestCase
{
    public function testItAuthenticatesAndAuthorizesUser(): void
    {
        $guard = new SessionGuard(new MemoryUserProvider(), '_fx_auth_test');
        self::assertTrue($guard->attempt(['email' => 'admin@example.com', 'password' => 'secret']));
        self::assertTrue($guard->check());

        $gate = new Gate($guard);
        $gate->define('admin', fn (TestUser $user): bool => $user->role === 'admin');
        self::assertTrue($gate->allows('admin'));
        $gate->authorize('admin');
        $guard->logout();
        self::assertTrue($guard->guest());
    }

    public function testItRejectsUnauthorizedAbility(): void
    {
        $this->expectException(AuthorizationException::class);
        (new Gate(new SessionGuard(new MemoryUserProvider(), '_fx_auth_missing')))->authorize('missing');
    }
}
