<?php
declare(strict_types=1);
namespace Fx\Framework\Tests;

use PHPUnit\Framework\TestCase;

final class PackageRepositoryTest extends TestCase
{
    public function testVersionsArePinnedPreservedAndCannotBeRewritten(): void
    {
        $directory=sys_get_temp_dir().'/fx-repository-'.bin2hex(random_bytes(8));
        $repo=$directory.'/repo'; mkdir($repo.'/tools',0700,true);
        copy(dirname(__DIR__).'/tools/package-repository.php',$repo.'/tools/package-repository.php');
        $run=static function(array $args)use($repo):array {
            $p=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$repo,null,['bypass_shell'=>true]);
            fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);
            return [proc_close($p),$out,$err];
        };
        try {
            foreach (['core','http'] as $name) {
                mkdir($repo.'/packages/'.$name.'/docs',0700,true);
                $manifest=['name'=>'fxfavalessa/fx-'.$name,'autoload'=>['psr-4'=>['Example\\'=>'src/']],'require'=>['php'=>'^8.1']];
                if ($name==='http') { $manifest['require']['fxfavalessa/fx-core']='^1.0 || dev-main'; }
                file_put_contents($repo.'/packages/'.$name.'/composer.json',json_encode($manifest));
                file_put_contents($repo.'/packages/'.$name.'/docs/index.html','<p>Ajuda</p>');
            }
            foreach ([['init','-q'],['config','user.name','Fixture'],['config','user.email','fixture@example.test'],['config','commit.gpgsign','false'],['add','.'],['commit','-qm','fixture']] as $args) {
                self::assertSame(0,$run(['git',...$args])[0]);
            }
            $build=static fn(string $out,string $version):array=>$run([PHP_BINARY,'tools/package-repository.php',$directory.'/'.$out,'https://github.com/example/test',$version]);
            [$code,,$error]=$build('first','1.0.0-rc.1'); self::assertSame(0,$code,$error);
            $first=json_decode(file_get_contents($directory.'/first/packages.json'),true);
            self::assertSame('1.0.0-rc.1',$first['packages']['fxfavalessa/fx-http']['1.0.0-rc.1']['require']['fxfavalessa/fx-core']);
            $plan=json_decode(file_get_contents($directory.'/first/publish-plan.json'),true);
            self::assertCount(2,$plan['tags']);
            self::assertSame('refs/tags/packages/core/v1.0.0-rc.1',$plan['tags'][0]['ref']);
            mkdir($repo.'/docs/composer',0700,true);
            copy($directory.'/first/packages.json',$repo.'/docs/composer/packages.json');
            self::assertSame(0,$run(['git','add','.'])[0]); self::assertSame(0,$run(['git','commit','-qm','index'])[0]);
            unlink($repo.'/docs/composer/packages.json'); // O indice do commit prevalece sobre exclusao local.
            self::assertSame(1,$build('duplicate','1.0.0-rc.1')[0]);
            self::assertDirectoryDoesNotExist($directory.'/duplicate');
            self::assertSame(1,$build('invalid','../bad')[0]);
            self::assertSame(0,$build('second','1.0.0-rc.2')[0]);
            $second=json_decode(file_get_contents($directory.'/second/packages.json'),true);
            foreach ($first['packages'] as $package=>$versions) {
                self::assertSame($versions['1.0.0-rc.1'],$second['packages'][$package]['1.0.0-rc.1']);
            }
            self::assertSame(0,$build('development','dev-main')[0]);
            $development=json_decode(file_get_contents($directory.'/development/packages.json'),true);
            self::assertSame($first['packages']['fxfavalessa/fx-core']['1.0.0-rc.1'],$development['packages']['fxfavalessa/fx-core']['1.0.0-rc.1']);
        } finally {
            $files=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
            foreach($files as $file) { if($file->isDir())rmdir($file->getPathname());else{chmod($file->getPathname(),0600);unlink($file->getPathname());} }
            rmdir($directory);
        }
    }
}
