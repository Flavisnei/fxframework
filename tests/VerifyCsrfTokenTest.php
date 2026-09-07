<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Foundation\Application;
use Fx\Framework\Http\Csrf;
use Fx\Framework\Http\Kernel;
use Fx\Framework\Http\Request;
use Fx\Framework\Middleware\VerifyCsrfToken;
use Fx\Framework\Routing\Router;
use PHPUnit\Framework\TestCase;

final class VerifyCsrfTokenTest extends TestCase
{
    private function kernel(bool &$called): Kernel
    {
        $app = new Application(__DIR__);
        $router = $app->make(Router::class);
        $router->middleware(VerifyCsrfToken::class);
        $router->any('/save', function () use (&$called): array {
            $called = true;
            return ['saved' => true];
        });
        return $app->make(Kernel::class);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeMethods')]
    public function testMissingTokenBlocksEveryUnsafeMethod(string $method): void
    {
        $called = false;
        $request = Request::create('/save', $method);
        $request->headers->set('Accept', 'application/json');
        $response = $this->kernel($called)->handle($request);
        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($called);
        self::assertSame(['message' => 'Token CSRF invalido.'], json_decode($response->getContent(), true));
    }

    public static function unsafeMethods(): array
    {
        return [['POST'], ['PUT'], ['PATCH'], ['DELETE']];
    }

    public function testFormAndJsonAndHeaderTokensPass(): void
    {
        $token = Csrf::regenerate();
        $requests = [
            Request::create('/save', 'POST', ['_csrf' => $token]),
            Request::create('/save', 'PATCH', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['_csrf' => $token])),
            Request::create('/save', 'DELETE', [], [], [], ['HTTP_X_CSRF_TOKEN' => $token]),
        ];
        foreach ($requests as $request) {
            $called = false;
            self::assertSame(200, $this->kernel($called)->handle($request)->getStatusCode());
            self::assertTrue($called);
        }
    }

    public function testInvalidArrayQueryAndRotatedTokensAreRejected(): void
    {
        $oldToken = Csrf::regenerate();
        $token = Csrf::regenerate();
        $requests = [
            Request::create('/save', 'POST', ['_csrf' => 'invalid']),
            Request::create('/save', 'POST', ['_csrf' => [$token]]),
            Request::create('/save?_csrf=' . $token, 'POST'),
            Request::create('/save', 'POST', ['_csrf' => $oldToken]),
        ];
        foreach ($requests as $request) {
            $called = false;
            self::assertSame(403, $this->kernel($called)->handle($request)->getStatusCode());
            self::assertFalse($called);
        }
    }

    public function testSafeMethodsDoNotStartSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        $middleware = new VerifyCsrfToken();
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            self::assertSame('ok', $middleware->process(Request::create('/save', $method), fn () => 'ok'));
            self::assertSame(PHP_SESSION_NONE, session_status());
        }
    }

    public function testBareRouterDoesNotImposeSessionAuthenticationOnApis(): void
    {
        $app = new Application(__DIR__);
        $app->make(Router::class)->post('/api', fn () => ['ok' => true]);
        self::assertSame(200, $app->make(Kernel::class)->handle(Request::create('/api', 'POST'))->getStatusCode());
    }
}
