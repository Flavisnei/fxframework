<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Http\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    public function testGeneratedTokenCanBeValidated(): void
    {
        $token = Csrf::regenerate('_csrf_test');

        self::assertTrue(Csrf::validate($token, '_csrf_test'));
        self::assertFalse(Csrf::validate('invalid', '_csrf_test'));
    }
}
