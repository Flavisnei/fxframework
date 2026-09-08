<?php
declare(strict_types=1);

// Um processo PHP novo por amostra. Nao carrega config de aplicacoes existentes.
$root = dirname(__DIR__);
$scenario = $argv[1] ?? '';
$vendors = ['core-minimal'=>'examples/minimal', 'core-complete'=>'.', 'http-json'=>'examples/http', 'contacts-page'=>'examples/contacts'];
if (!isset($vendors[$scenario])) { fwrite(STDERR, "Cenario desconhecido.\n"); exit(1); }
$autoload = $root . '/' . $vendors[$scenario] . '/vendor/autoload.php';
if (!is_file($autoload)) { fwrite(STDERR, "Instale o vendor do cenario antes de medir.\n"); exit(1); }
$start = hrtime(true);
require $autoload;
$autoloadMs = (hrtime(true) - $start) / 1e6;
$operation = null; $cleanup = null;

if (str_starts_with($scenario, 'core-')) {
    $operation = static function () use ($root): string {
        $app = new Fx\Framework\Foundation\CoreApplication($root);
        $app->config()->set('benchmark.value', 42);
        $app->bind('answer', static fn () => 42); $app->boot();
        if ($app->make('answer') !== 42) { throw new RuntimeException('Core retornou resultado incorreto.'); }
        return '';
    };
} elseif ($scenario === 'http-json') {
    $operation = static function () use ($root): string {
        $app = new Fx\Framework\Foundation\Application($root);
        $app->make(Fx\Framework\Routing\Router::class)->get('/bench', static fn () => ['ok'=>true,'value'=>42]);
        $app->boot();
        $response = $app->make(Fx\Framework\Http\Kernel::class)->handle(Fx\Framework\Http\Request::create('/bench', 'GET'));
        if ($response->getStatusCode() !== 200 || json_decode($response->getContent(), true) !== ['ok'=>true,'value'=>42]) { throw new RuntimeException('HTTP retornou resultado incorreto.'); }
        return $response->getContent();
    };
} else {
    // Preparacao fora da medicao: banco sintetico, 1000 contatos e usuario em memoria.
    $directory = sys_get_temp_dir() . '/fx-bench-' . bin2hex(random_bytes(8));
    if (!mkdir($directory, 0700)) { throw new RuntimeException('Falha ao criar diretorio temporario.'); }
    session_save_path($directory);
    $session = new Fx\Framework\Admin\AdminSession(false, 1800, 28800, 'FXBENCH'); $session->start();
    $cleanup = static function () use ($directory): void { if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); } rmdir($directory); };
    register_shutdown_function($cleanup);
    $admin = new Fx\Framework\Admin\AdminStore(new PDO('sqlite::memory:'), ['contacts.view']);
    $admin->install('Benchmark', 'benchmark@example.test', 'synthetic password 123');
    $session->login($admin, $admin->retrieveById(1));
    $db = new PDO('sqlite::memory:'); $contacts = new Example\Contacts\ContactStore($db); $contacts->install();
    $db->beginTransaction(); $insert = $db->prepare('INSERT INTO contacts(name,email,phone,notes) VALUES (?,?,?,?)');
    for ($i=1;$i<=1000;$i++) { $insert->execute(['Contato ' . $i, 'contact' . $i . '@example.test', '', 'Nota sintetica']); }
    $db->commit();
    $app = new Fx\Framework\Foundation\Application($root);
    $panel = new Fx\Framework\Admin\Panel($admin, $session, new Fx\Framework\Modules\ModuleManager($root));
    $panel->api($app->make(Fx\Framework\Routing\Router::class), 'GET', 'contacts', 'contacts.view', static fn ($request) => $contacts->listing($request->query->all()));
    $app->boot();
    $operation = static function () use ($app): string {
        $response = $app->make(Fx\Framework\Http\Kernel::class)->handle(Fx\Framework\Http\Request::create('/admin/api/contacts?page=1', 'GET'));
        $data = json_decode($response->getContent(), true);
        if ($response->getStatusCode() !== 200 || ($data['total'] ?? null) !== 1000 || count($data['data'] ?? []) !== 20) { throw new RuntimeException('Paginacao retornou resultado incorreto.'); }
        return $response->getContent();
    };
}
$before = memory_get_usage(false);
$start = hrtime(true); $body = $operation(); $operationMs = (hrtime(true) - $start) / 1e6;
echo json_encode(['autoload_ms'=>$autoloadMs, 'operation_ms'=>$operationMs, 'memory_before_bytes'=>$before, 'memory_after_bytes'=>memory_get_usage(false), 'process_peak_bytes'=>memory_get_peak_usage(true), 'body_bytes'=>strlen($body)], JSON_THROW_ON_ERROR) . PHP_EOL;
