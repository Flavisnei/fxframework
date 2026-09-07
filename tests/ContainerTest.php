<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Foundation\Application;
use Fx\Framework\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;

interface MessageRepository
{
    public function message(): string;
}

final class MemoryMessageRepository implements MessageRepository
{
    public function message(): string
    {
        return 'ok';
    }
}

final class MessageService
{
    public function __construct(public MessageRepository $repository)
    {
    }
}

final class TestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MessageRepository::class, MemoryMessageRepository::class);
    }
}

final class ContainerTest extends TestCase
{
    public function testItResolvesConstructorDependencies(): void
    {
        $app = new Application(__DIR__);
        $app->register(TestServiceProvider::class);
        $app->boot();

        $service = $app->make(MessageService::class);

        self::assertSame('ok', $service->repository->message());
        self::assertSame(
            $service->repository,
            $app->make(MessageRepository::class)
        );
    }

    public function testItInjectsDependenciesWhenCallingMethods(): void
    {
        $app = new Application(__DIR__);
        $app->bind(MessageRepository::class, MemoryMessageRepository::class);

        $result = $app->call(
            static fn (MessageRepository $repository): string => $repository->message()
        );

        self::assertSame('ok', $result);
    }
}
