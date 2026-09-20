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
    // Evolucao da minima: repositorio local completo apenas no consumidor temporario.
    $target=$temporary.'/none';
    $manifest=json_decode(file_get_contents($target.'/composer.json'),true);
    $versions=[];foreach(glob($root.'/packages/*/composer.json') as $file){$package=json_decode(file_get_contents($file),true);$versions[$package['name']]='dev-main';}
    $manifest['repositories']=[['type'=>'path','url'=>str_replace('\\','/',$root).'/packages/*','options'=>['symlink'=>false,'versions'=>$versions]]];
    file_put_contents($target.'/composer.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    file_put_contents($target.'/.env',"PRESERVE_TEST=1\n");
    setupRun([PHP_BINARY,$root.'/fxartisan','setup:upgrade','--target='.$target,'--without-admin','--constraint=@dev','--yes','--no-interaction','--composer='.$composer],$root);
    setupAssert(file_get_contents($target.'/.env')==="PRESERVE_TEST=1\n");
    setupAssert(!is_file($target.'/config/admin.php'));
    setupAssert(str_contains(setupRun([PHP_BINARY,$target.'/fxartisan','route:list'],$root),'/api/status'));
    $probe=<<<'PHP'
<?php
$app=require __DIR__.'/bootstrap/app.php';
if(class_exists(Fx\Framework\Admin\Panel::class))exit(10);
$kernel=$app->make(Fx\Framework\Http\Kernel::class);
$response=$kernel->handle(Fx\Framework\Http\Request::create('/','GET'));
if($response->getStatusCode()!==200 || !str_contains($response->getContent(),'Controller, serviço e view'))exit(11);
$response=$kernel->handle(Fx\Framework\Http\Request::create('/api/status','GET'));
if(json_decode($response->getContent(),true)['status']!=='ok')exit(12);
file_put_contents(__DIR__.'/resources/views/probe.tpl','Olá -{$nome|escape}-');
if($app->make(Fx\Framework\View\View::class)->render('probe.tpl',['nome'=>'<FX>'])!=='Olá &lt;FX&gt;')exit(13);
PHP;
    file_put_contents($target.'/probe-upgrade.php',$probe);setupRun([PHP_BINARY,'probe-upgrade.php'],$target);$checks++;
    $pdo=new PDO('sqlite:'.$sqlite);setupAssert((int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn()===0);$pdo=null;
    if($mysql){$statement=$mysql->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=?');$statement->execute([$database]);setupAssert((int)$statement->fetchColumn()===0);}

    foreach(['complete'=>[], 'custom'=>['--components=http,validation'], 'wordpress'=>[]] as $profile=>$extra) {
        $target=$temporary.'/profile-'.$profile;
        setupRun([PHP_BINARY,$root.'/examples/console/vendor/bin/fxartisan','setup:init',$target,'--profile='.$profile,'--database=none','--yes','--no-interaction','--core-path='.$root.'/packages/core','--composer='.$composer,...$extra],$root);
        setupAssert(is_file($target.'/LEIA-ME.txt'));
        if($profile==='complete') {
            setupAssert(!is_file($target.'/storage/admin.sqlite'));
            putenv('FX_SETUP_TEST_PASSWORD='.bin2hex(random_bytes(16)));
            try {
                setupRun([PHP_BINARY,'configure.php','--email=setup@example.test','--password-env=FX_SETUP_TEST_PASSWORD','--no-interaction'],$target);
                $probe=<<<'PHP'
<?php
$app=require __DIR__.'/bootstrap.php';
$db=new PDO('sqlite:'.__DIR__.'/storage/admin.sqlite');
$user=$db->query('SELECT email,password FROM fx_admin_users')->fetch(PDO::FETCH_ASSOC);
if($user['email']!=='setup@example.test' || !password_verify(getenv('FX_SETUP_TEST_PASSWORD'),$user['password']))exit(4);
$response=$app->make(Fx\Framework\Http\Kernel::class)->handle(Fx\Framework\Http\Request::create('/admin','GET'));
if($response->getStatusCode()!==200 || !str_contains($response->getContent(),'Entrar'))exit(5);
PHP;
                file_put_contents($target.'/probe.php',$probe);setupRun([PHP_BINARY,'probe.php'],$target);$checks++;
            } finally {putenv('FX_SETUP_TEST_PASSWORD');}
        } elseif($profile==='custom') {
            setupAssert(str_contains(setupRun([PHP_BINARY,'public/index.php'],$target),'FX HTTP pronto'));
            setupAssert(!is_file($target.'/configure.php'));
        } else {
            setupAssert(is_file($target.'/plugin.php') && !is_file($target.'/.env'));
            setupAssert(setupRun([PHP_BINARY,'plugin.php'],$target)==='');
            $probe=<<<'PHP'
<?php
$hooks=[];$menu=false;
define('ABSPATH',__DIR__.'/');
function add_action($hook,$callback,...$args){global $hooks;$hooks[$hook][]=$callback;}
function plugin_basename($file){return 'fx-test/plugin.php';}
function current_user_can($permission){return true;}
function add_management_page($title,$label,$capability,$slug,$callback){global $menu;$menu=$capability==='manage_options';ob_start();$callback();$html=ob_get_clean();if(!str_contains($html,'Integracao ativa'))exit(7);}
require __DIR__.'/plugin.php';
foreach($hooks['plugins_loaded']??[] as $callback)$callback();
foreach($hooks['admin_menu']??[] as $callback)$callback();
if(!$menu)exit(6);
PHP;
            file_put_contents($target.'/probe.php',$probe);setupRun([PHP_BINARY,'probe.php'],$target);$checks++;
        }
    }
    echo "OK: {$checks} verificacoes de instalacao real; minima sem tabelas e perfis adicionais verificados.\n";
} finally {
    if($mysql)$mysql->exec('DROP DATABASE `'.$database.'`');$mysql=null;
    // Somente a pasta aleatoria criada por este teste.
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $item){$item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());}rmdir($temporary);
}
