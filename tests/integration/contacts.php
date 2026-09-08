<?php
declare(strict_types=1);

// Usa exclusivamente o autoload do exemplo isolado. Nao toca em contas existentes.
$example = dirname(__DIR__, 2) . '/examples/contacts';
require $example . '/vendor/autoload.php';
$temporary = sys_get_temp_dir() . '/fx-contacts-review-' . bin2hex(random_bytes(8));
foreach (['config', 'public', 'modules/admin/docs', 'modules/admin/resources'] as $directory) { mkdir($temporary . '/' . $directory, 0775, true); }
$package = Composer\InstalledVersions::getInstallPath('fxfavalessa/fx-admin');
copy($package . '/fx-module.json', $temporary . '/modules/admin/fx-module.json');
copy($package . '/docs/index.html', $temporary . '/modules/admin/docs/index.html');
foreach (['modules/contacts/docs', 'modules/contacts/resources', 'app/Console'] as $directory) { mkdir($temporary . '/' . $directory, 0770, true); }
foreach (['modules/contacts/fx-module.json', 'modules/contacts/docs/index.html', 'app/Console/commands.php'] as $file) { copy($example . '/' . $file, $temporary . '/' . $file); }
file_put_contents($temporary . '/config/modules.json', '{"schema":1,"manifests":["modules/admin/fx-module.json","modules/contacts/fx-module.json"]}');
file_put_contents($temporary . '/config/admin.php', '<?php return ["database" => dirname(__DIR__) . "/storage/admin.sqlite", "secure_cookie" => false, "permissions" => ["contacts.view","contacts.create","contacts.update","contacts.delete"]];');
// MariaDB opt-in: conta do ambiente, banco aleatório e descarte ao finalizar.
if (getenv('FX_TEST_ADMIN_MYSQL') === '1') {
    $mysqlPort=(int)(getenv('FX_TEST_MYSQL_PORT') ?: 3306);
    $mysqlUser=getenv('FX_TEST_MYSQL_USER') ?: 'root';$mysqlPass=getenv('FX_TEST_MYSQL_PASSWORD') ?: '';
    $mysqlServer=new PDO('mysql:host=127.0.0.1;port='.$mysqlPort,$mysqlUser,$mysqlPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $mysqlName='fx_http_test_'.bin2hex(random_bytes(8));
    $mysqlServer->exec('CREATE DATABASE `'.$mysqlName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
    register_shutdown_function(static function()use($mysqlServer,$mysqlName,$temporary):void {
        $mysqlServer->exec('DROP DATABASE `'.$mysqlName.'`');
        foreach (['admin.php','contacts.php'] as $file) { if(is_file($temporary.'/config/'.$file)) unlink($temporary.'/config/'.$file); }
    });
    $mysqlConfig=['driver'=>'mysql','host'=>'127.0.0.1','port'=>$mysqlPort,'name'=>$mysqlName,'username'=>$mysqlUser,'password'=>$mysqlPass];
    file_put_contents($temporary.'/config/admin.php','<?php return '.var_export(['database'=>$mysqlConfig,'secure_cookie'=>false,'permissions'=>['contacts.view','contacts.create','contacts.update','contacts.delete']],true).';');
    file_put_contents($temporary.'/config/contacts.php','<?php return '.var_export(['database'=>$mysqlConfig],true).';');
}
$autoload = var_export($example . '/vendor/autoload.php', true);
file_put_contents($temporary . '/public/index.php', '<?php require ' . $autoload . '; $root=dirname(__DIR__); $app=new Fx\Framework\Foundation\Application($root); $app->instance(Fx\Framework\Admin\Panel::class, Fx\Framework\Admin\AdminConfig::panel($root)); (new Fx\Framework\Modules\ModuleManager($root))->register($app); $app->boot(); $app->make(Fx\Framework\Http\Kernel::class)->handle()->send();');
$password = bin2hex(random_bytes(16));
putenv('FX_ADMIN_TEST_PASSWORD=' . $password);
$artisan = new Fx\Framework\Console\Artisan($temporary);
$artisan->setAutoExit(false);
$output = new Symfony\Component\Console\Output\BufferedOutput();
$input = new Symfony\Component\Console\Input\ArrayInput(['command' => 'admin:init', '--email' => 'admin@example.test', '--password-env' => 'FX_ADMIN_TEST_PASSWORD']);
$input->setInteractive(false);
$checks = 0;
function adminCheck(bool $value, string $message): void { global $checks; if (!$value) { throw new RuntimeException($message); } $checks++; }
adminCheck($artisan->run($input, $output) === 0, 'admin:init falhou: ' . $output->fetch());
putenv('FX_ADMIN_TEST_PASSWORD');
adminCheck($artisan->run(new Symfony\Component\Console\Input\ArrayInput(['command'=>'contacts:init']), $output) === 0, 'contacts:init falhou.');
(new Fx\Framework\Modules\ModuleManager($temporary))->enable('contacts');
foreach (['fx-database', 'fx-view', 'fx-wordpress'] as $name) { adminCheck(!Composer\InstalledVersions::isInstalled('fxfavalessa/' . $name), 'Dependencia inesperada: ' . $name); }
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($socket === false) { throw new RuntimeException($errstr); }
$address = stream_socket_get_name($socket, false); fclose($socket);
$process = proc_open([PHP_BINARY, '-S', $address, '-t', $temporary . '/public', $temporary . '/public/index.php'], [0 => ['pipe', 'r'], 1 => ['file', $temporary . '/server.log', 'a'], 2 => ['file', $temporary . '/server.log', 'a']], $pipes, $temporary, null, ['bypass_shell' => true]);
if (!is_resource($process)) { throw new RuntimeException('Servidor nao iniciou.'); }
fclose($pipes[0]);
$cookies = [];
function adminHttp(string $path, ?array $data = null, string $csrf = '', ?string $method = null): array
{
    global $address, $cookies;
    $header = ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-TOKEN: ' . $csrf];
    if ($cookies) { $header[] = 'Cookie: ' . implode('; ', array_map(fn ($name, $value) => $name . '=' . $value, array_keys($cookies), $cookies)); }
    $context = stream_context_create(['http' => ['method' => $method ?? ($data === null ? 'GET' : 'POST'), 'header' => implode("\r\n", $header), 'content' => $data === null ? '' : json_encode($data), 'ignore_errors' => true, 'timeout' => 10]]);
    $body = file_get_contents('http://' . $address . $path, false, $context);
    $headers = $http_response_header ?? [];
    preg_match('/HTTP\/\S+ (\d+)/', $headers[0] ?? '', $match);
    foreach ($headers as $line) { if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i', $line, $cookie)) { $cookies[$cookie[1]] = $cookie[2]; } }
    return [(int) ($match[1] ?? 0), $body, $headers];
}
try {
    for ($attempt = 0; $attempt < 30; $attempt++) { $ready = @stream_socket_client('tcp://' . $address, $errno, $errstr, .1); if ($ready) { fclose($ready); break; } usleep(100000); }
    [$status, $body, $headers] = adminHttp('/admin/api/session');
    adminCheck($status === 200, 'Sessao HTTP falhou: ' . $body);
    $csrf = json_decode($body, true)['csrf'];
    adminCheck(str_contains(strtolower(implode(' ', $headers)), 'httponly'), 'Cookie sem HttpOnly.');
    adminCheck(str_contains(strtolower(implode(' ', $headers)), 'samesite=lax'), 'Cookie sem SameSite.');
    $oldCookies = $cookies;
    adminCheck(adminHttp('/admin/api/login', ['email' => 'admin@example.test', 'password' => $password])[0] === 403, 'Login aceitou ausencia de CSRF.');
    [$status, $body] = adminHttp('/admin/api/login', ['email' => 'admin@example.test', 'password' => $password], $csrf);
    adminCheck($status === 200, 'Login real falhou: ' . $body);
    adminCheck($oldCookies !== $cookies, 'Sessao nao foi regenerada.');
    $csrf = json_decode($body, true)['csrf'];
    [$status, $body] = adminHttp('/admin/api/users');
    adminCheck($status === 200 && json_decode($body, true)['total'] === 1 && !str_contains($body, 'password'), 'Listagem autenticada invalida.');
    foreach (['/admin', '/admin/admin.js', '/admin/admin.css', '/admin/fxwindows.js', '/admin/help'] as $path) { [$status, $body] = adminHttp($path); adminCheck($status === 200 && strlen($body) > 100, 'Recurso falhou: ' . $path); }
    [$status,$body] = adminHttp('/admin/api/session');
    adminCheck($status === 200 && json_decode($body,true)['areas'][0]['id'] === 'contacts', 'Cartao de contatos ausente.');
    $contact = ['name'=>'Contato HTTP','email'=>'http@example.test','phone'=>'','notes'=>'Somente teste'];
    adminCheck(adminHttp('/admin/api/contacts',$contact)[0] === 403,'Criacao sem CSRF aceita.');
    [$status,$body] = adminHttp('/admin/api/contacts',$contact,$csrf);
    $created = json_decode($body,true);
    adminCheck($status === 200 && $created['version'] === 1,'Criacao HTTP falhou: ' . $body);
    [$status,$body] = adminHttp('/admin/api/contacts?page=1');
    adminCheck($status === 200 && json_decode($body,true)['total'] === 1 && !str_contains($body,'notes'),'Lista HTTP invalida.');
    $edit = [...$contact,'id'=>$created['id'],'version'=>1,'name'=>'Contato alterado'];
    [$status,$body] = adminHttp('/admin/api/contacts',$edit,$csrf,'PUT');
    adminCheck($status === 200 && json_decode($body,true)['version'] === 2,'PUT HTTP falhou.');
    adminCheck(adminHttp('/admin/api/contacts',$edit,$csrf,'PUT')[0] === 409,'PUT antigo aceito.');
    adminCheck(adminHttp('/admin/api/contacts',['id'=>$created['id'],'version'=>2],$csrf,'DELETE')[0] === 200,'DELETE HTTP falhou.');
    adminCheck(adminHttp('/admin/api/contacts/read',['id'=>$created['id']],$csrf)[0] === 404,'Exclusao nao persistiu.');
    foreach (['/contacts','/contacts/app.js','/contacts/help'] as $path) { [$status,$body] = adminHttp($path); adminCheck($status === 200 && strlen($body)>100,'Recurso do modulo ausente: ' . $path); }
    adminHttp('/admin/api/contacts',$contact,$csrf);
    (new Fx\Framework\Modules\ModuleManager($temporary))->disable('contacts');
    adminCheck(adminHttp('/admin/api/contacts')[0] === 404,'Modulo desativado manteve API.');
    (new Fx\Framework\Modules\ModuleManager($temporary))->enable('contacts');
    [$status,$body] = adminHttp('/admin/api/contacts');
    adminCheck($status === 200 && json_decode($body,true)['total'] === 1,'Desativacao perdeu dados.');
    adminCheck($artisan->run(new Symfony\Component\Console\Input\ArrayInput(['command'=>'contacts:init']),$output) === 0,'Inicializacao repetida falhou.');
    [$status,$body] = adminHttp('/admin/api/contacts');
    adminCheck($status === 200 && json_decode($body,true)['total'] === 1,'Inicializacao repetida perdeu dados.');
    adminCheck(adminHttp('/admin/api/logout', [], $csrf)[0] === 200, 'Logout falhou.');
    adminCheck(adminHttp('/admin/api/users')[0] === 401, 'Logout nao encerrou autenticacao.');
    echo "OK: {$checks} verificacoes HTTP/CLI com autoload isolado.\nTemporarios: {$temporary}\n";
} finally { proc_terminate($process); proc_close($process); }
