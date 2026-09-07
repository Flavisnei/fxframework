<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Foundation\Application;
use Fx\Framework\Http\Request;
use Fx\Framework\Routing\ControllerDispatcher;
use PHPUnit\Framework\TestCase;

final class ExampleController
{
    public function update(Request $request, string $id): array
    {
        return [
            'id' => $id,
            'name' => $request->input('name'),
        ];
    }

    private function hidden(): void
    {
    }
}

final class ExampleFormRequest extends Request
{
}

final class FormRequestController
{
    public function store(ExampleFormRequest $request): string
    {
        return (string) $request->input('name');
    }
}

final class ControllerDispatcherTest extends TestCase
{
    public function testItInjectsRequestAndNamedRouteParameters(): void
    {
        $app = new Application(__DIR__);
        $request = Request::create('/clientes/42', 'PUT', ['name' => 'Maria']);

        $result = (new ControllerDispatcher($app))->dispatch(
            ExampleController::class,
            'update',
            ['id' => '42'],
            $request
        );

        self::assertSame(['id' => '42', 'name' => 'Maria'], $result);
        self::assertSame($request, $app->make(Request::class));
    }

    public function testItRejectsNonPublicControllerMethods(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ControllerDispatcher(new Application(__DIR__)))->dispatch(
            ExampleController::class,
            'hidden',
            [],
            Request::create('/')
        );
    }

    public function testItCopiesInputIntoRequestSubclasses(): void
    {
        $result = (new ControllerDispatcher(new Application(__DIR__)))->dispatch(
            FormRequestController::class,
            'store',
            [],
            Request::create('/users', 'POST', ['name' => 'Maria'])
        );

        self::assertSame('Maria', $result);
    }
}
