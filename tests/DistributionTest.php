<?php
declare(strict_types=1);
namespace Fx\Framework\Tests;

use PHPUnit\Framework\TestCase;

final class DistributionTest extends TestCase
{
    public function testSnapshotFiltersDataAndUsesCommitInsteadOfWorkingTree(): void
    {
        if (!class_exists(\ZipArchive::class)) { self::markTestSkipped('Extensao ZIP necessaria para testar distribuicao.'); }
        $root = sys_get_temp_dir() . '/fx-distribution-' . bin2hex(random_bytes(8));
        mkdir($root . '/repo/tools', 0700, true);
        copy(dirname(__DIR__) . '/tools/package.php', $root . '/repo/tools/package.php');
        $run = static function (array $args, string $cwd): array {
            $process = proc_open($args, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $cwd, null, ['bypass_shell'=>true]);
            fclose($pipes[0]); $out=stream_get_contents($pipes[1]); fclose($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[2]);
            return [proc_close($process),$out,$err];
        };
        try {
            $repo = $root . '/repo';
            foreach (['README.md'=>'committed', '.env'=>'SYNTHETIC_SECRET', '.env.example'=>'EXAMPLE', 'storage/test.sqlite'=>'SYNTHETIC_DB', 'vendor/test.php'=>'SYNTHETIC_VENDOR', 'config/mail.local.json'=>'SYNTHETIC_MAIL'] as $file=>$text) {
                if (!is_dir(dirname($repo . '/' . $file))) mkdir(dirname($repo . '/' . $file),0700,true);
                file_put_contents($repo . '/' . $file,$text);
            }
            self::assertSame(0,$run(['git','init','-q'],$repo)[0]);
            self::assertSame(0,$run(['git','add','.'],$repo)[0]);
            self::assertSame(0,$run(['git','-c','user.name=Fixture','-c','user.email=fixture@example.test','-c','commit.gpgsign=false','commit','-qm','fixture'],$repo)[0]);
            file_put_contents($repo . '/README.md','UNCOMMITTED');
            file_put_contents($repo . '/untracked.txt','UNTRACKED');
            [$code,$output,$error]=$run([PHP_BINARY,'tools/package.php',$root . '/output'],$repo);
            self::assertSame(0,$code,$error); $result=json_decode($output,true,32,JSON_THROW_ON_ERROR);
            self::assertSame(hash_file('sha256',$result['archive']),$result['sha256']);
            self::assertStringStartsWith($result['sha256'],file_get_contents($result['archive'].'.sha256'));
            $zip=new \ZipArchive(); self::assertTrue($zip->open($result['archive']));
            self::assertSame('committed',$zip->getFromName('README.md'));
            self::assertSame('EXAMPLE',$zip->getFromName('.env.example'));
            foreach (['.env','storage/','vendor/','storage/test.sqlite','vendor/test.php','config/mail.local.json','untracked.txt'] as $file) self::assertFalse($zip->locateName($file),$file);
            self::assertSame($result['commit'],json_decode($zip->getFromName('FX-DISTRIBUTION.json'),true)['commit']); $zip->close();
            self::assertSame(1,$run([PHP_BINARY,'tools/package.php',$root . '/output'],$repo)[0]);
            self::assertSame(1,$run([PHP_BINARY,'tools/package.php',$repo . '/inside'],$repo)[0]);
            self::assertSame($result['sha256'],hash_file('sha256',$result['archive']));
        } finally {
            $files=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) { if ($file->isDir()) rmdir($file->getPathname()); else { chmod($file->getPathname(),0600); unlink($file->getPathname()); } }
            rmdir($root);
        }
    }
}
