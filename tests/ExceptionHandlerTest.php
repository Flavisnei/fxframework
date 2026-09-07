<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Http\ExceptionHandler;
use Fx\Framework\Http\Request;
use Fx\Framework\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

final class ExceptionHandlerTest extends TestCase
{
    public function testMissingModelBecomes404WithoutLeakingModelDetails(): void
    {
        $request = Request::create('/missing');
        $request->headers->set('Accept', 'application/json');
        $exception = (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel('PrivateUser', [42]);
        $response = (new ExceptionHandler())->render($exception, $request);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['message' => 'Not Found'], json_decode($response->getContent(), true));
    }

    public function testHttpExceptionsPreserveStatusAndHeaders(): void
    {
        $response = (new ExceptionHandler())->render(
            new \Symfony\Component\HttpKernel\Exception\HttpException(429, 'Try later', null, ['Retry-After' => '30'])
        );
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('30', $response->headers->get('Retry-After'));
        self::assertSame('Try later', $response->getContent());
    }

    public function testHttpServerErrorsRemainPrivate(): void
    {
        $response = (new ExceptionHandler())->render(
            new \Symfony\Component\HttpKernel\Exception\HttpException(503, 'private server details', null, ['Retry-After' => '60'])
        );
        self::assertSame(503, $response->getStatusCode());
        self::assertSame('Service Unavailable', $response->getContent());
        self::assertSame('60', $response->headers->get('Retry-After'));
    }

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
