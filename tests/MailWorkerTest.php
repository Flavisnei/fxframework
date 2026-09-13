<?php
declare(strict_types=1);
namespace Fx\Framework\Tests;
use Fx\Framework\Console\Commands\AdminMailCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
final class MailWorkerTest extends TestCase
{
    public function testFiniteWorkerPausesWhenDisabledAndValidatesOptions(): void
    {
        $root=sys_get_temp_dir().'/fx-worker-'.bin2hex(random_bytes(6));mkdir($root.'/config',0770,true);
        file_put_contents($root.'/config/admin.php', '<?php return '.var_export(['database'=>$root.'/unused.sqlite','mail_settings'=>true],true).';');
        file_put_contents($root.'/.env', 'FX_MAIL_ENABLED="0"');
        try {
            $tester=new CommandTester(new AdminMailCommand($root));
            self::assertSame(0,$tester->execute(['--watch'=>true,'--cycles'=>'2','--interval'=>'1']));
            self::assertSame(2,substr_count($tester->getDisplay(),'"paused":true'));
            self::assertFileDoesNotExist($root.'/unused.sqlite');
            $this->expectException(\RuntimeException::class);
            $tester->execute(['--watch'=>true,'--status'=>true]);
        } finally {unlink($root.'/.env');unlink($root.'/config/admin.php');rmdir($root.'/config');rmdir($root);}
    }
}
