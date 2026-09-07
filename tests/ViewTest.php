<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\View\View;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fx-view-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/templates', 0777, true);
        mkdir($this->directory . '/compile', 0777, true);
        mkdir($this->directory . '/cache', 0777, true);
        file_put_contents($this->directory . '/templates/example.tpl', '-{$name|default:"none"}-');
    }

    public function testRenderDataDoesNotLeakIntoNextRender(): void
    {
        $view = new View(
            $this->directory . '/templates',
            $this->directory . '/compile',
            $this->directory . '/cache'
        );

        self::assertSame('Maria', $view->render('example.tpl', ['name' => 'Maria']));
        self::assertSame('none', $view->render('example.tpl'));
    }

    public function testFxWindowsHelpersAreRegisteredAutomatically(): void
    {
        file_put_contents(
            $this->directory . '/templates/fxwindows.tpl',
            '-{fxwindows_assets forms=true}- -{fxwindow tipo="button" id="clientes" texto="Novo cliente" url="/clientes/novo" foco="#nome"}-'
        );

        $view = new View(
            $this->directory . '/templates',
            $this->directory . '/compile',
            $this->directory . '/cache'
        );

        $html = $view->render('fxwindows.tpl');
        self::assertStringContainsString('/assets/fxwindows/fxwindows.js?v=1.2.3', $html);
        self::assertStringContainsString('startFxForms()', $html);
        self::assertStringContainsString('data-window-id="clientes"', $html);
        self::assertStringContainsString('data-window-focus="#nome"', $html);
    }
}
