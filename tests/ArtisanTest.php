<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Console\Artisan;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ArtisanTest extends TestCase
{
    public function testItListsFrameworkCommands(): void
    {
        $artisan = new Artisan(__DIR__);
        $artisan->setAutoExit(false);
        $output = new BufferedOutput();

        $status = $artisan->run(new ArrayInput(['command' => 'list']), $output);

        self::assertSame(0, $status);
        self::assertStringContainsString('route:list', $output->fetch());
    }

    public function testAboutDisplaysFrameworkInformation(): void
    {
        $artisan = new Artisan(__DIR__);
        $artisan->setAutoExit(false);
        $output = new BufferedOutput();

        $status = $artisan->run(new ArrayInput(['command' => 'about']), $output);

        self::assertSame(0, $status);
        self::assertStringContainsString('FX Framework', $output->fetch());
    }
}
