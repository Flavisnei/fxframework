<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Validation\ValidationException;
use Fx\Framework\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    public function testItReturnsOnlyValidatedFields(): void
    {
        $data = Validator::make(
            ['name' => 'Maria', 'email' => 'maria@example.com', 'admin' => true],
            ['name' => 'required|string|min:3', 'email' => 'required|email']
        )->validated();

        self::assertSame(['name' => 'Maria', 'email' => 'maria@example.com'], $data);
    }

    public function testItThrowsStructuredErrors(): void
    {
        try {
            Validator::make(['email' => 'invalid'], ['name' => 'required', 'email' => 'email'])->validated();
            self::fail('ValidationException esperada.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('name', $exception->errors());
            self::assertArrayHasKey('email', $exception->errors());
        }
    }
}
