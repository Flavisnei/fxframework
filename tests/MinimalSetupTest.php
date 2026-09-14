<?php
declare(strict_types=1);
namespace Fx\Framework\Tests;
use Fx\Framework\Console\Installation\MinimalSetup;
use Fx\Framework\Console\Commands\SetupInitCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Dotenv\Dotenv;
final class MinimalSetupTest extends TestCase
{
    private string $root;
    protected function setUp():void { $this->root=sys_get_temp_dir().'/fx-setup-'.bin2hex(random_bytes(8));mkdir($this->root); }
    protected function tearDown():void {
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as $item) { $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname()); } rmdir($this->root);
    }
    public function testWithoutDatabaseHasOnlyCoreAndAdaptedGuide():void {
        $target=(new MinimalSetup())->create($this->root.'/minimal',null);
        $manifest=json_decode(file_get_contents($target.'/composer.json'),true);
        self::assertSame(['php','fxfavalessa/fx-core'],array_keys($manifest['require']));
        self::assertFileDoesNotExist($target.'/.env');self::assertFileDoesNotExist($target.'/app/Connection.php');
        self::assertStringContainsString('SEM BANCO',file_get_contents($target.'/LEIA-ME.txt'));
        self::assertStringContainsString('App\\',file_get_contents($target.'/LEIA-ME.txt'));
        $this->expectException(\RuntimeException::class);(new MinimalSetup())->create($target,null);
    }
    public function testDatabaseEnvironmentRoundTripDoesNotCreateTablesOrLeakPassword():void {
        $file=$this->root.'/existing.sqlite';$pdo=new \PDO('sqlite:'.$file);$pdo=null;
        $secret='test $HOME "quoted" \\ # =';
        $db=['driver'=>'sqlite','host'=>'','port'=>'','database'=>realpath($file),'username'=>'','password'=>$secret];
        MinimalSetup::testDatabase($db);$target=(new MinimalSetup())->create($this->root.'/database',$db);
        $env=(new Dotenv())->parse(file_get_contents($target.'/.env'));
        self::assertSame($secret,$env['DB_PASSWORD']);
        foreach(['LEIA-ME.txt','.env.example','composer.json','docs/index.html'] as $name)self::assertStringNotContainsString($secret,file_get_contents($target.'/'.$name));
        $manifest=json_decode(file_get_contents($target.'/composer.json'),true);
        self::assertArrayNotHasKey('fxfavalessa/fx-database',$manifest['require']);
        self::assertArrayNotHasKey('illuminate/database',$manifest['require']);
        $pdo=new \PDO('sqlite:'.$file);self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn());$pdo=null;
    }
    public function testDryRunAndCancelledPromptLeaveNoDirectory():void {
        $tester=new CommandTester((new \Fx\Framework\Console\Artisan($this->root))->find('setup:init'));
        self::assertSame(0,$tester->execute(['target'=>$this->root.'/dry','--database'=>'none','--dry-run'=>true],['interactive'=>false]));
        self::assertDirectoryDoesNotExist($this->root.'/dry');
        $tester->setInputs(['1','n','n']);self::assertSame(0,$tester->execute(['target'=>$this->root.'/cancel']));
        self::assertDirectoryDoesNotExist($this->root.'/cancel');
    }
    public function testMissingSqliteIsRejectedWithoutCreatingFileOrProject():void {
        $tester=new CommandTester((new \Fx\Framework\Console\Artisan($this->root))->find('setup:init'));
        try { $tester->execute(['target'=>$this->root.'/bad','--database'=>'sqlite','--db-name'=>$this->root.'/missing.sqlite','--yes'=>true,'--no-install'=>true],['interactive'=>false]);self::fail('Banco inexistente aceito'); }
        catch(\RuntimeException $error) { self::assertStringContainsString('SQLite',$error->getMessage()); }
        self::assertFileDoesNotExist($this->root.'/missing.sqlite');self::assertDirectoryDoesNotExist($this->root.'/bad');
    }
    public function testNoInstallCommandGeneratesRunnableClassWithoutCredentials():void {
        $tester=new CommandTester((new \Fx\Framework\Console\Artisan($this->root))->find('setup:init'));
        self::assertSame(0,$tester->execute(['target'=>$this->root.'/generated','--database'=>'none','--yes'=>true,'--no-install'=>true],['interactive'=>false]));
        require $this->root.'/generated/app/Saudacao.php';self::assertSame('Olá, FX!',(new \App\Saudacao())->mensagem('FX'));
    }
    public function testProfilesGenerateDistinctStructuresAndResolveAdminConsole():void {
        $setup=new MinimalSetup();
        $complete=$setup->create($this->root.'/complete',null,null,'complete');
        self::assertFileExists($complete.'/configure.php');self::assertFileExists($complete.'/public/index.php');
        self::assertFileDoesNotExist($complete.'/storage/admin.sqlite');
        self::assertStringNotContainsString('Você escolheu SEM BANCO',file_get_contents($complete.'/LEIA-ME.txt'));
        $manifest=json_decode(file_get_contents($complete.'/composer.json'),true);
        self::assertArrayHasKey('fxfavalessa/fx-admin',$manifest['require']);self::assertArrayNotHasKey('fxfavalessa/fx-database',$manifest['require']);
        $custom=$setup->create($this->root.'/custom',null,null,'custom',['http','validation']);
        self::assertFileExists($custom.'/public/index.php');self::assertFileDoesNotExist($custom.'/configure.php');
        $wordpress=$setup->create($this->root.'/wordpress',null,null,'wordpress');
        self::assertFileExists($wordpress.'/plugin.php');self::assertFileDoesNotExist($wordpress.'/.env');
        self::assertSame(['core','admin','console'],\Fx\Framework\Console\Installation\SetupProfile::components('custom',['admin']));
    }
    public function testInvalidProfileDoesNotCreateDestination():void {
        try {(new MinimalSetup())->create($this->root.'/bad-profile',null,null,'unknown');self::fail('Perfil invalido aceito');}
        catch(\RuntimeException) {self::assertDirectoryDoesNotExist($this->root.'/bad-profile');}
    }
    public function testInteractiveProfileSelectionSkipsDatabaseForWordpress():void {
        $tester=new CommandTester((new \Fx\Framework\Console\Artisan($this->root))->find('setup:init'));
        $tester->setInputs(['4','s']);
        self::assertSame(0,$tester->execute(['target'=>$this->root.'/wp-menu','--no-install'=>true]));
        self::assertFileExists($this->root.'/wp-menu/plugin.php');
        self::assertStringNotContainsString('Deseja configurar uma conexao',$tester->getDisplay());
    }

}
