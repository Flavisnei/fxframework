<?php
declare(strict_types=1);
// Consumidor independente; configuracao privada chega por stdin.
$config=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
require $config['consumer'].'/vendor/autoload.php';
$db=Fx\Framework\Admin\AdminConfig::connection(['database'=>$config['database']]);
$store=new Fx\Framework\Admin\AdminStore($db);
if (($argv[1]??'')==='create') { $store->install('Release Test','release@example.test',$config['password']); }
$user=$store->retrieveByCredentials(['email'=>'release@example.test']);
if (!$user || !$store->validateCredentials($user,['password'=>$config['password']]) || count($store->users(1,'')['data'])!==1) {
    throw new RuntimeException('Conta nao preservada durante troca de versao.');
}
$app=new Fx\Framework\Foundation\CoreApplication($config['consumer']);
if ($app->config()->get('absent','ok')!=='ok') { throw new RuntimeException('Core invalido.'); }
if (class_exists(Illuminate\Database\Capsule\Manager::class) || class_exists(Smarty::class)) { throw new RuntimeException('Dependencias opcionais inesperadas.'); }
echo json_encode(['account_preserved'=>true,'core'=>true,'no_eloquent_or_smarty'=>true],JSON_THROW_ON_ERROR);
