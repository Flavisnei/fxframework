<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Console\Artisan;
use Fx\Framework\Foundation\CoreApplication;
use Fx\Framework\Modules\ModuleManager;
use Fx\Framework\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ModuleBaseProvider extends ServiceProvider
{
    public function register(): void { $this->app->instance('module.base', true); }
}
final class ModuleSalesProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!$this->app->make('module.base')) { throw new \RuntimeException('Ordem incorreta.'); }
        $this->app->instance('module.sales', true);
    }
}

final class ModuleManagerTest extends TestCase
{
    private string $root;
    private ModuleManager $manager;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/fx-modules-test-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/config', 0775, true);
        foreach (['base', 'sales'] as $id) {
            mkdir($this->root . '/modules/' . $id, 0775, true);
            file_put_contents($this->root . '/modules/' . $id . '/help.html', '<h1>Ajuda</h1>');
            $this->manifest($id, ['schema' => 1, 'id' => $id, 'version' => '1.0.0', 'provider' => $id === 'base' ? ModuleBaseProvider::class : ModuleSalesProvider::class, 'requires' => $id === 'base' ? [] : ['base'], 'help' => 'help.html']);
        }
        file_put_contents($this->root . '/config/modules.json', json_encode(['schema' => 1, 'manifests' => ['modules/sales/fx-module.json', 'modules/base/fx-module.json']]));
        $this->manager = new ModuleManager($this->root);
    }

    private function manifest(string $id, array $changes): void
    {
        $file = $this->root . '/modules/' . $id . '/fx-module.json';
        $current = is_file($file) ? json_decode(file_get_contents($file), true) : [];
        file_put_contents($file, json_encode(array_replace($current, $changes)));
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        rmdir($this->root);
    }

    public function testEnablePersistsDependenciesAndRegistersInOrderWithoutBooting(): void
    {
        self::assertSame(['base' => '1.0.0', 'sales' => '1.0.0'], $this->manager->enable('sales'));
        $app = new CoreApplication($this->root);
        (new ModuleManager($this->root))->register($app);
        self::assertTrue($app->make('module.sales'));
        self::assertFalse($app->isBooted());
        self::assertCount(2, $app->providers());
        $this->manager->register($app);
        self::assertCount(2, $app->providers());
    }

    public function testDependentBlocksDisableAndDataIsPreserved(): void
    {
        $this->manager->enable('sales');
        $before = file_get_contents($this->root . '/storage/framework/modules.json');
        try { $this->manager->disable('base'); self::fail('Dependente deveria bloquear.'); }
        catch (\RuntimeException $error) { self::assertStringContainsString('sales depende', $error->getMessage()); }
        self::assertSame($before, file_get_contents($this->root . '/storage/framework/modules.json'));
        self::assertSame(['base' => '1.0.0'], $this->manager->disable('sales'));
        self::assertFileExists($this->root . '/modules/sales/help.html');
        self::assertSame([], $this->manager->disable('base'));
    }

    public function testCycleAndMissingDependencyCannotWriteActiveState(): void
    {
        foreach ([['sales'], ['unknown']] as $requires) {
            $this->manifest('base', ['requires' => $requires]);
            try { $this->manager->enable('sales'); self::fail('Grafo invalido deveria falhar.'); }
            catch (\RuntimeException $error) { self::assertMatchesRegularExpression('/circular|nao registrado/', $error->getMessage()); }
            self::assertSame([], $this->manager->enabled());
        }
    }

    public function testInvalidProviderFailsBeforeAnyProviderIsRegistered(): void
    {
        $this->manager->enable('sales');
        $this->manifest('sales', ['provider' => 'MissingModuleProvider']);
        $app = new CoreApplication($this->root);
        try { $this->manager->register($app); self::fail('Provider ausente deveria falhar.'); }
        catch (\RuntimeException $error) { self::assertStringContainsString('Provider ausente', $error->getMessage()); }
        self::assertCount(0, $app->providers());
    }

    public function testUpdatedVersionRequiresExplicitRefresh(): void
    {
        $this->manager->enable('sales');
        $this->manifest('sales', ['version' => '1.1.0']);
        try { $this->manager->doctor(); self::fail('Versao alterada deveria falhar.'); }
        catch (\RuntimeException $error) { self::assertStringContainsString('module:refresh', $error->getMessage()); }
        self::assertSame('1.0.0', $this->manager->enabled()['sales']);
        self::assertSame('1.1.0', $this->manager->refresh()['sales']);
        self::assertCount(2, $this->manager->doctor());
    }

    public function testRefreshDoesNotSilentlyEnableNewDependencies(): void
    {
        $this->manager->enable('base');
        $this->manifest('base', ['version' => '1.1.0', 'requires' => ['sales']]);
        $this->manifest('sales', ['requires' => []]);
        try { $this->manager->refresh(); self::fail('Nova dependencia inativa deveria falhar.'); }
        catch (\RuntimeException $error) { self::assertStringContainsString('Dependencia desativada', $error->getMessage()); }
        self::assertSame(['base' => '1.0.0'], $this->manager->enabled());
        $this->manager->enable('sales');
        self::assertSame('1.0.0', $this->manager->enabled()['base']);
        self::assertSame('1.1.0', $this->manager->refresh()['base']);
    }

    public function testCorruptStateIsNeverTreatedAsEmpty(): void
    {
        $this->manager->enable('base');
        file_put_contents($this->root . '/storage/framework/modules.json', '{broken');
        $this->expectException(\RuntimeException::class);
        $this->manager->enable('sales');
    }

    public function testManifestRejectsEscapingHelpAndDuplicateProviders(): void
    {
        $this->manifest('sales', ['help' => '../base/help.html']);
        try { $this->manager->definitions(); self::fail('Recurso externo deveria falhar.'); }
        catch (\RuntimeException $error) { self::assertStringContainsString('fora do modulo', $error->getMessage()); }
        $this->manifest('sales', ['help' => 'help.html', 'provider' => ModuleBaseProvider::class]);
        $this->expectExceptionMessage('Provider compartilhado');
        $this->manager->definitions();
    }

    public function testNoConfigurationHasNoFilesystemSideEffects(): void
    {
        unlink($this->root . '/config/modules.json');
        self::assertSame([], $this->manager->doctor());
        $this->manager->register(new CoreApplication($this->root));
        self::assertDirectoryDoesNotExist($this->root . '/storage');
    }

    public function testCliOperationsUseTheSuppliedRoot(): void
    {
        $artisan = new Artisan($this->root);
        $artisan->setAutoExit(false);
        foreach ([['module:enable', ['id' => 'sales'], 0], ['module:doctor', [], 0], ['module:status', [], 0], ['module:disable', ['id' => 'base'], 1], ['module:disable', ['id' => 'sales'], 0]] as [$command, $args, $expected]) {
            $output = new BufferedOutput();
            self::assertSame($expected, $artisan->run(new ArrayInput(['command' => $command] + $args), $output), $output->fetch());
        }
        self::assertSame(['base' => '1.0.0'], $this->manager->enabled());
    }

    public function testRegistrationAfterBootIsRejected(): void
    {
        $app = new CoreApplication($this->root);
        $app->boot();
        $this->expectExceptionMessage('antes de boot');
        $this->manager->register($app);
    }
}
