<?php

declare(strict_types=1);

$scenario = $argv[1] ?? '';
$absent = [
    'http' => ['database', 'view', 'console', 'auth', 'windows'],
    'database' => ['http', 'view', 'console', 'auth', 'windows', 'core'],
    'view' => ['http', 'database', 'console', 'auth', 'windows', 'core'],
    'console' => ['http', 'database', 'view', 'auth', 'windows'],
    'auth' => ['database', 'view', 'console', 'windows'],
    'validation' => ['http', 'database', 'view', 'auth', 'console', 'windows', 'core'],
    'windows' => ['http', 'database', 'view', 'auth', 'console', 'core'],
    'web' => ['database', 'view', 'auth', 'windows'],
    'modular' => [],
];
if (!array_key_exists($scenario, $absent)) { throw new InvalidArgumentException('Informe um cenario conhecido.'); }
require __DIR__ . '/' . $scenario . '/vendor/autoload.php';

function check(bool $value, string $message): void
{
    if (!$value) { throw new RuntimeException($message); }
}

$classes = [
    'http' => Fx\Framework\Foundation\Application::class,
    'core' => Fx\Framework\Foundation\CoreApplication::class,
    'database' => Fx\Framework\Database\Database::class,
    'view' => Fx\Framework\View\View::class,
    'console' => Fx\Framework\Console\Artisan::class,
    'auth' => Fx\Framework\Auth\SessionGuard::class,
    'windows' => Fx\Framework\Windows\Assets::class,
];
check(!Composer\InstalledVersions::isInstalled('fxfavalessa/fx-framework'), 'Pacote completo vazou para a instalacao modular.');
foreach ($absent[$scenario] as $package) {
    check(!Composer\InstalledVersions::isInstalled('fxfavalessa/fx-' . $package), 'Dependencia inesperada: ' . $package);
    check(!class_exists($classes[$package]), 'Classe inesperada: ' . $package);
}
if (in_array('database', $absent[$scenario], true)) { check(!class_exists(Illuminate\Database\Capsule\Manager::class), 'Eloquent inesperado.'); }
if (in_array('view', $absent[$scenario], true)) { check(!class_exists(Smarty::class), 'Smarty inesperado.'); }
if (in_array('http', $absent[$scenario], true)) { check(!class_exists(Illuminate\Http\Request::class), 'Request Illuminate inesperado.'); }

$temporary = sys_get_temp_dir() . '/fx-package-check-' . bin2hex(random_bytes(8));
mkdir($temporary);
$previous = getcwd();
try {
    if (class_exists(Fx\Framework\Validation\Validator::class)) {
        check(Fx\Framework\Validation\Validator::make(['name' => 'ação'], ['name' => 'string|max:4'])->passes(), 'Validacao indisponivel.');
    }
    if (class_exists($classes['http'])) {
        $app = new Fx\Framework\Foundation\Application($temporary);
        $app->make(Fx\Framework\Routing\Router::class)->get('/ping', fn () => ['ok' => true]);
        $response = $app->make(Fx\Framework\Http\Kernel::class)->handle(Fx\Framework\Http\Request::create('/ping'));
        check($response->getContent() === '{"ok":true}', 'HTTP nao respondeu.');
        $error = (new Fx\Framework\Http\ExceptionHandler())->render(new RuntimeException('private'));
        check($error->getStatusCode() === 500, 'Handler depende de pacote ausente.');
    }
    if (class_exists($classes['database'])) {
        $capsule = Fx\Framework\Database\Database::boot(['driver' => 'sqlite', 'database' => ':memory:']);
        $connection = $capsule->getConnection();
        $connection->getSchemaBuilder()->create('items', fn ($table) => $table->string('name'));
        $connection->table('items')->insert(['name' => 'teste']);
        check($connection->table('items')->count() === 1, 'Banco indisponivel.');
    }
    if (class_exists($classes['view'])) {
        foreach (['templates', 'compile', 'cache'] as $dir) { mkdir($temporary . '/' . $dir); }
        file_put_contents($temporary . '/templates/test.tpl', '-{$name|escape:"html"}-');
        $view = new Fx\Framework\View\View($temporary . '/templates', $temporary . '/compile', $temporary . '/cache');
        check($view->render('test.tpl', ['name' => '<FX>']) === '&lt;FX&gt;', 'View indisponivel.');
        if (!class_exists($classes['http'])) {
            file_put_contents($temporary . '/templates/csrf.tpl', '-{csrf_field}-');
            try { $view->render('csrf.tpl'); throw new LogicException('CSRF deveria exigir HTTP.'); }
            catch (RuntimeException $exception) { check(str_contains($exception->getMessage(), 'fxfavalessa/fx-http'), 'Diagnostico CSRF incorreto.'); }
        }
    }
    if (class_exists($classes['auth'])) {
        $provider = new class implements Fx\Framework\Auth\UserProvider {
            public function retrieveById(string|int $identifier): ?Fx\Framework\Auth\Authenticatable { return null; }
            public function retrieveByCredentials(array $credentials): ?Fx\Framework\Auth\Authenticatable { return null; }
            public function validateCredentials(Fx\Framework\Auth\Authenticatable $user, array $credentials): bool { return false; }
        };
        check(!(new Fx\Framework\Auth\SessionGuard($provider))->attempt([]), 'Guard incorreto.');
    }
    if (class_exists($classes['windows'])) {
        check(is_file(Fx\Framework\Windows\Assets::directory() . '/fxwindows.js'), 'Assets ausentes.');
    }
    if (class_exists($classes['console'])) {
        chdir($temporary);
        $artisan = new Fx\Framework\Console\Artisan($temporary);
        check($artisan->has('about'), 'Console indisponivel.');
        check($artisan->has('migrate') === class_exists($classes['database']), 'Comandos de banco incorretos.');
        check($artisan->has('app:init') === class_exists($classes['http']), 'Comandos HTTP incorretos.');
        check($artisan->has('fxwindows:install') === class_exists($classes['windows']), 'Comando assets incorreto.');
        $artisan->setAutoExit(false);
        $output = new Symfony\Component\Console\Output\BufferedOutput();
        if ($artisan->has('app:init')) {
            ob_start();
            try { check($artisan->run(new Symfony\Component\Console\Input\ArrayInput(['command' => 'app:init']), $output) === 0, 'app:init falhou.'); }
            finally { ob_end_clean(); }
            $generated = require $temporary . '/bootstrap/app.php';
            check($generated instanceof Fx\Framework\Foundation\Application, 'Bootstrap exige banco ausente.');
        }
        if ($artisan->has('fxwindows:install')) {
            check($artisan->run(new Symfony\Component\Console\Input\ArrayInput(['command' => 'fxwindows:install']), $output) === 0, 'Publicacao falhou.');
            check(is_file($temporary . '/public/assets/fxwindows/fxwindows.js'), 'JS nao publicado.');
        }
    }
} finally {
    if (is_string($previous)) { chdir($previous); }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
    rmdir($temporary);
}
echo $scenario . ': instalacao isolada OK' . PHP_EOL;
