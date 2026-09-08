<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Console\Artisan;
use Fx\Framework\Console\Installation\PackageCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class PackageInstallationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/fx install ' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        file_put_contents($this->directory . '/composer.json', '{}');
        file_put_contents($this->directory . '/composer test.phar', '<?php file_put_contents("args.json", json_encode([getcwd(), $argv])); fwrite(STDERR, "<error>literal</error>\n"); exit(is_file("fail") ? 2 : 0);');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') as $file) { unlink($file); }
        rmdir($this->directory);
    }

    private function runCommand(array $arguments): array
    {
        $app = new Artisan($this->directory);
        $app->setAutoExit(false);
        $output = new BufferedOutput();
        $status = $app->run(new ArrayInput($arguments + ['--composer' => $this->directory . '/composer test.phar']), $output);
        return [$status, $output->fetch()];
    }

    public function testComposerUsesTargetDirectoryAndSeparateArgumentsWithSpaces(): void
    {
        [$status, $output] = $this->runCommand(['command' => 'module:install', 'name' => 'fxfavalessa/fx-view', '--constraint' => '^1.0 || dev-main', '--dry-run' => true]);
        self::assertSame(0, $status, $output);
        [$cwd, $argv] = json_decode(file_get_contents($this->directory . '/args.json'), true);
        self::assertSame(realpath($this->directory), realpath($cwd));
        self::assertContains('fxfavalessa/fx-view:^1.0 || dev-main', $argv);
        self::assertContains('--dry-run', $argv);
        self::assertContains('--no-plugins', $argv);
        self::assertContains('--no-scripts', $argv);
        self::assertStringContainsString('<error>literal</error>', $output);
    }

    public function testPresetInstallsItsPackagesInOneComposerTransaction(): void
    {
        [$status, $output] = $this->runCommand(['command' => 'preset:install', 'name' => 'api', '--constraint' => '@dev']);
        self::assertSame(0, $status, $output);
        $argv = json_decode(file_get_contents($this->directory . '/args.json'), true)[1];
        self::assertContains('fxfavalessa/fx-http:@dev', $argv);
        self::assertContains('fxfavalessa/fx-console:@dev', $argv);
        self::assertNotContains('--dry-run', $argv);
    }

    public function testComposerFailureIsNotReportedAsSuccess(): void
    {
        touch($this->directory . '/fail');
        [$status, $output] = $this->runCommand(['command' => 'module:install', 'name' => 'core']);
        self::assertSame(1, $status);
        self::assertStringContainsString('Composer falhou', $output);
        self::assertStringNotContainsString('Pacotes instalados.', $output);
    }

    public function testUnknownComponentsAndPresetsNeverInvokeComposer(): void
    {
        foreach ([['module:install', '--help'], ['module:install', 'core;echo'], ['preset:install', 'unknown']] as [$command, $name]) {
            [$status] = $this->runCommand(['command' => $command, 'name' => $name]);
            self::assertNotSame(0, $status);
            self::assertFileDoesNotExist($this->directory . '/args.json');
        }
    }

    public function testMissingManifestNeverInvokesComposer(): void
    {
        unlink($this->directory . '/composer.json');
        [$status, $output] = $this->runCommand(['command' => 'module:install', 'name' => 'core']);
        self::assertNotSame(0, $status);
        self::assertStringContainsString('composer.json ausente', $output);
        self::assertFileDoesNotExist($this->directory . '/args.json');
    }

    public function testEnvironmentCannotRedirectManifest(): void
    {
        $previous = getenv('COMPOSER');
        putenv('COMPOSER=other.json');
        try {
            [$status, $output] = $this->runCommand(['command' => 'module:install', 'name' => 'core']);
            self::assertNotSame(0, $status);
            self::assertStringContainsString('Remova COMPOSER', $output);
            self::assertFileDoesNotExist($this->directory . '/args.json');
        } finally { putenv($previous === false ? 'COMPOSER' : 'COMPOSER=' . $previous); }
    }

    public function testMinimalAndWordPressDoNotSelectHttpOrDashboard(): void
    {
        self::assertSame(['fxfavalessa/fx-core'], PackageCatalog::preset('minimal'));
        self::assertSame(['fxfavalessa/fx-wordpress'], PackageCatalog::preset('wordpress'));
    }
}
