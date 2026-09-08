<?php

declare(strict_types=1);
namespace Fx\Framework\Tests;

use Fx\Framework\Console\Artisan;
use Fx\Framework\Console\Installation\InstallCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class InstallCatalogTest extends TestCase
{
    private string $directory;
    private array $data = ['schema' => 1, 'components' => ['crm' => ['package' => 'example/crm', 'description' => 'CRM de teste']], 'presets' => ['office' => ['crm']]];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/fx-catalog-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        file_put_contents($this->directory . '/composer.json', '{}');
        file_put_contents($this->directory . '/composer.phar', '<?php file_put_contents("args.json", json_encode($argv));');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') as $file) { unlink($file); }
        rmdir($this->directory);
    }

    private function fixture(?array $data = null): string
    {
        $file = $this->directory . '/catalog.json';
        file_put_contents($file, json_encode($data ?? $this->data, JSON_THROW_ON_ERROR));
        return $file;
    }

    public function testDefaultCatalogPreservesExistingPackagesAndPresets(): void
    {
        self::assertSame('fxfavalessa/fx-core', InstallCatalog::load()->package('core'));
        self::assertSame(['fxfavalessa/fx-http', 'fxfavalessa/fx-console'], InstallCatalog::load()->preset('api'));
    }

    public function testExternalPackageAndPresetUseVerifiedFile(): void
    {
        $file = $this->fixture();
        $catalog = InstallCatalog::load($file, hash_file('sha256', $file));
        self::assertSame('example/crm', $catalog->package('crm'));
        self::assertSame('example/crm', $catalog->package('example/crm'));
        self::assertSame(['example/crm'], $catalog->preset('office'));
    }

    public function testTamperedFileIsRejectedBeforeComposerStarts(): void
    {
        $file = $this->fixture();
        $hash = hash_file('sha256', $file);
        file_put_contents($file, ' ', FILE_APPEND);
        [$code, $text] = $this->runCommand(['command'=>'module:install', 'name'=>'crm', '--catalog'=>$file, '--catalog-sha256'=>$hash]);
        self::assertNotSame(0, $code);
        self::assertStringContainsString('SHA-256', $text);
        self::assertFileDoesNotExist($this->directory . '/args.json');
    }

    public function testCatalogNeverAddsRepositoriesOrArbitraryRequirements(): void
    {
        $invalid = [
            ['schema'=>2],
            ['repositories'=>[['type'=>'vcs','url'=>'https://example.test/repo']]],
            ['components'=>['crm'=>['package'=>'--no-scripts','description'=>'test']]],
            ['components'=>['crm'=>['package'=>'example/crm:^1.0','description'=>'test']]],
            ['components'=>['crm'=>['package'=>'example/crm','description'=>'<error>test</error>']]],
            ['presets'=>['office'=>['absent']]],
            ['presets'=>['office'=>['crm','crm']]],
            ['presets'=>['office'=>['nested'=>['crm']]]],
        ];
        foreach ($invalid as $change) {
            [$code] = $this->runCommand(['command'=>'preset:install', 'name'=>'office', '--catalog'=>$this->fixture(array_replace($this->data, $change))]);
            self::assertNotSame(0, $code);
            self::assertFileDoesNotExist($this->directory . '/args.json');
        }
    }

    public function testUntrustedTransportsAndMissingRemoteHashFailWithoutNetwork(): void
    {
        foreach (['http://example.test/catalog.json', 'php://memory', 'https://example.test/catalog.json'] as $source) {
            [$code] = $this->runCommand(['command'=>'module:install', 'name'=>'crm', '--catalog'=>$source]);
            self::assertNotSame(0, $code);
            self::assertFileDoesNotExist($this->directory . '/args.json');
        }
    }

    public function testOversizedAndMalformedFilesFailWithoutComposer(): void
    {
        foreach ([str_repeat(' ', 262145), '{invalid'] as $json) {
            $file = $this->fixture(); file_put_contents($file, $json);
            [$code] = $this->runCommand(['command'=>'module:install', 'name'=>'crm', '--catalog'=>$file]);
            self::assertNotSame(0, $code);
            self::assertFileDoesNotExist($this->directory . '/args.json');
        }
    }

    public function testExternalPresetIsPassedInOneSafeComposerInvocation(): void
    {
        [$code, $text] = $this->runCommand(['command'=>'preset:install', 'name'=>'office', '--catalog'=>$this->fixture(), '--constraint'=>'^2.0 || ^3.0', '--dry-run'=>true]);
        self::assertSame(0, $code, $text);
        $args = json_decode(file_get_contents($this->directory . '/args.json'), true);
        self::assertContains('example/crm:^2.0 || ^3.0', $args);
        foreach (['--no-scripts','--no-plugins','--dry-run'] as $flag) { self::assertContains($flag, $args); }
        self::assertSame('{}', file_get_contents($this->directory . '/composer.json'));
    }

    public function testListUsesSelectedCatalogWithoutExecutingComposer(): void
    {
        $app = new Artisan($this->directory); $app->setAutoExit(false); $output = new BufferedOutput();
        self::assertSame(0, $app->run(new ArrayInput(['command'=>'module:list','--catalog'=>$this->fixture()]), $output));
        self::assertStringContainsString('example/crm', $output->fetch());
        self::assertFileDoesNotExist($this->directory . '/args.json');
    }

    private function runCommand(array $args): array
    {
        $app = new Artisan($this->directory); $app->setAutoExit(false); $output = new BufferedOutput();
        $code = $app->run(new ArrayInput($args + ['--composer'=>$this->directory . '/composer.phar']), $output);
        return [$code, $output->fetch()];
    }
}
