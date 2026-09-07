<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Http\ExceptionHandler;
use Fx\Framework\Http\Request;
use Fx\Framework\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

final class ExceptionHandlerTest extends TestCase
{
    public function testItRendersValidationErrorsAsJson(): void
    {
        $request = Request::create('/users', 'POST');
        $request->headers->set('Accept', 'application/json');
        $response = (new ExceptionHandler())->render(
            new ValidationException(['email' => ['Email invalido.']]),
            $request
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"message":"Os dados informados sao invalidos.","errors":{"email":["Email invalido."]}}',
            (string) $response->getContent()
        );
    }

    public function testItHidesInternalExceptionDetailsOutsideDebugMode(): void
    {
        $response = (new ExceptionHandler())->render(new \RuntimeException('database password leaked'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', $response->getContent());
    }
}
