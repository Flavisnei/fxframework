<?php
declare(strict_types=1);
namespace Fx\Framework\Tests;
use Fx\Framework\Admin\AdminStore;
use PHPUnit\Framework\TestCase;
final class MariaDbConcurrencyTest extends TestCase
{
    use DatabaseBackend;
    public function testConcurrentDemotionsResetConsumptionAndThrottle(): void
    {
        if(getenv('FX_TEST_ADMIN_MYSQL')!=='1') { self::markTestSkipped('Ative FX_TEST_ADMIN_MYSQL=1 para regressao concorrente real.'); }
        $db=$this->backend();$store=new AdminStore($db);$store->install('Admin','a1@example.test','initial secret 123');
        self::assertSame(2,$store->saveRole(null,['name'=>'Leitor','permissions'=>[]]));
        self::assertSame(2,$store->saveUser(null,['name'=>'Admin 2','email'=>'a2@example.test','password'=>'initial secret 123','role_id'=>1,'active'=>true]));
        self::assertSame([200,409],$this->race('demote'));
        self::assertSame(1,(int)$db->query('SELECT COUNT(*) FROM fx_admin_users WHERE role_id=1 AND active=1')->fetchColumn());
        $token=$store->resetToken($store->retrieveById(1));
        self::assertSame([200,422],$this->race('reset',$token));
        self::assertSame([200,429],$this->race('throttle'));
    }
    private function race(string $action,string $token=''): array
    {
        $directory=sys_get_temp_dir().'/fx-admin-race-'.bin2hex(random_bytes(8));mkdir($directory,0700);$processes=[];$channels=[];
        try {
            foreach([1,2] as $id) {
                $p=proc_open([PHP_BINARY,__DIR__.'/integration/admin-race-worker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),null,['bypass_shell'=>true]);
                self::assertIsResource($p);$processes[$id]=$p;$channels[$id]=$pipes;
                fwrite($pipes[0],json_encode(['database'=>$this->backendConfig,'directory'=>$directory,'id'=>$id,'action'=>$action,'token'=>$token],JSON_THROW_ON_ERROR));fclose($pipes[0]);
            }
            $deadline=microtime(true)+20;
            while(count(glob($directory.'/ready-*'))!==2){if(microtime(true)>$deadline)throw new \RuntimeException('Barreira expirou.');usleep(10000);clearstatcache();}
            touch($directory.'/start');$results=[];
            foreach($processes as $id=>$p){$out=stream_get_contents($channels[$id][1]);$err=stream_get_contents($channels[$id][2]);fclose($channels[$id][1]);fclose($channels[$id][2]);unset($channels[$id]);$exit=proc_close($p);unset($processes[$id]);self::assertSame(0,$exit);self::assertSame('',$err);$results[]=(int)$out;}
            sort($results);return $results;
        } finally {
            foreach($processes as $p){proc_terminate($p);proc_close($p);}
            foreach($channels as $pipes){foreach([1,2] as $index)if(is_resource($pipes[$index]))fclose($pipes[$index]);}
            foreach(glob($directory.'/*') as $file)unlink($file);rmdir($directory);
        }
    }
}
