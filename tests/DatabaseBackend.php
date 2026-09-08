<?php
declare(strict_types=1);
namespace Fx\Framework\Tests;

/** Testes opt-in no MariaDB; cria somente bancos aleatórios por caso. */
trait DatabaseBackend
{
    private ?\PDO $backendServer = null;
    private ?array $backendConfig = null;
    private function backend(string $sqlite = 'sqlite::memory:'): \PDO
    {
        if (getenv('FX_TEST_ADMIN_MYSQL') !== '1') { return new \PDO($sqlite); }
        if ($this->backendConfig === null) {
            $port=(int)(getenv('FX_TEST_MYSQL_PORT') ?: 3306);
            $user=getenv('FX_TEST_MYSQL_USER') ?: 'root';$pass=getenv('FX_TEST_MYSQL_PASSWORD') ?: '';
            $this->backendServer=new \PDO('mysql:host=127.0.0.1;port='.$port,$user,$pass,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
            $name='fx_admin_test_'.bin2hex(random_bytes(8));
            $this->backendServer->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
            $this->backendConfig=['driver'=>'mysql','host'=>'127.0.0.1','port'=>$port,'name'=>$name,'username'=>$user,'password'=>$pass];
        }
        return \Fx\Framework\Admin\AdminConfig::connection(['database'=>$this->backendConfig]);
    }
    #[\PHPUnit\Framework\Attributes\After]
    protected function removeBackend(): void
    {
        if ($this->backendConfig !== null) { $this->backendServer->exec('DROP DATABASE `'.$this->backendConfig['name'].'`'); }
        $this->backendConfig=null;$this->backendServer=null;
    }
}
