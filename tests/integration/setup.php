<?php
declare(strict_types=1);
// CLI isolado e projetos descartaveis; nunca aponta para o exemplo Contatos.
$root=dirname(__DIR__,2);
$temporary=sys_get_temp_dir().'/fx-setup-integration-'.bin2hex(random_bytes(6));mkdir($temporary);
$composer=$argv[1]??null;
if(!$composer || !is_file($composer))throw new RuntimeException('Informe o caminho de composer.phar.');
$checks=0;
function setupRun(array $command,string $cwd):string {
    $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['redirect',1]],$pipes,$cwd,null,['bypass_shell'=>true]);
    if(!is_resource($process))throw new RuntimeException('Processo nao iniciou.');fclose($pipes[0]);$output=stream_get_contents($pipes[1]);fclose($pipes[1]);
    if(proc_close($process)!==0)throw new RuntimeException('Falha na integracao de instalacao: '.$output);
    return $output;
}
function setupAssert(bool $value):void {global $checks;if(!$value)throw new RuntimeException('Verificacao da instalacao falhou.');$checks++;}
$mysql=null;$database='fx_setup_'.bin2hex(random_bytes(6));
try {
    $sqlite=$temporary.'/existing.sqlite';$pdo=new PDO('sqlite:'.$sqlite);$pdo=null;
    $variants=['none'=>['--database=none'],'sqlite'=>['--database=sqlite','--db-name='.$sqlite]];
    if(getenv('FX_TEST_SETUP_MYSQL')==='1') {
        $mysql=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $mysql->exec('CREATE DATABASE `'.$database.'`');
        $variants['mariadb']=['--database=mariadb','--db-name='.$database,'--db-user=root','--db-no-password'];
    }
    foreach($variants as $name=>$options) {
        $target=$temporary.'/'.$name;
        setupRun([PHP_BINARY,$root.'/examples/console/vendor/bin/fxartisan','setup:init',$target,'--yes','--no-interaction','--core-path='.$root.'/packages/core','--composer='.$composer,...$options],$root);
        setupAssert(str_contains(setupRun([PHP_BINARY,'example.php'],$target),'Olá, FX!'));
        $manifest=json_decode(file_get_contents($target.'/composer.json'),true);
        setupAssert(!isset($manifest['require']['fxfavalessa/fx-database']));
        $probe="<?php require __DIR__.'/vendor/autoload.php';";
        $probe.="if (class_exists('Illuminate\\Database\\Capsule\\Manager') || class_exists('Fx\\Framework\\Admin\\Panel')) exit(2);";
        if($name!=='none')$probe.="\$pdo=App\\Connection::open(); if ((int)\$pdo->query('SELECT 1')->fetchColumn()!==1)exit(3);";
        file_put_contents($target.'/probe.php',$probe);setupRun([PHP_BINARY,'probe.php'],$target);$checks++;
        setupAssert(is_file($target.'/.env')===($name!=='none'));
        setupAssert(str_contains(file_get_contents($target.'/LEIA-ME.txt'),$name==='none'?'SEM BANCO':'UPDATE contatos'));
    }
    $pdo=new PDO('sqlite:'.$sqlite);setupAssert((int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn()===0);$pdo=null;
    if($mysql){$statement=$mysql->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=?');$statement->execute([$database]);setupAssert((int)$statement->fetchColumn()===0);}
    echo "OK: {$checks} verificacoes de instalacao real; bancos sem tabelas.\n";
} finally {
    if($mysql)$mysql->exec('DROP DATABASE `'.$database.'`');$mysql=null;
    // Somente a pasta aleatoria criada por este teste.
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $item){$item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());}rmdir($temporary);
}
