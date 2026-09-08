<?php
declare(strict_types=1);
namespace Fx\Framework\Tests;
use Fx\Framework\Admin\AdminConfig;
use PHPUnit\Framework\TestCase;
final class AdminConfigTest extends TestCase
{
    public function testInvalidDatabaseConfigurationIsRejectedBeforeConnecting(): void
    {
        foreach ([null, 'relative.sqlite', ['driver'=>'mysql','host'=>'127.0.0.1;dbname=other','name'=>'test','username'=>'root','password'=>''], ['driver'=>'mysql','host'=>'127.0.0.1','name'=>'test','username'=>'root','password'=>'','port'=>'3306']] as $database) {
            try {AdminConfig::connection(['database'=>$database]);self::fail('Configuracao invalida aceita.');}
            catch(\RuntimeException $e){self::assertStringContainsString('database',$e->getMessage());}
        }
    }
    public function testSqliteConnectionPreservesExplicitCreation(): void
    {
        $file=sys_get_temp_dir().'/fx-config-'.bin2hex(random_bytes(8)).'.sqlite';
        try {
            try {AdminConfig::connection(['database'=>$file]);self::fail('Arquivo ausente aceito.');}catch(\RuntimeException $e){self::assertStringContainsString('ausente',$e->getMessage());}
            $db=AdminConfig::connection(['database'=>$file],true);self::assertSame('sqlite',$db->getAttribute(\PDO::ATTR_DRIVER_NAME));$db=null;
        } finally {if(is_file($file))unlink($file);}
    }
}
