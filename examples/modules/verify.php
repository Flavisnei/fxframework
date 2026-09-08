<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

function checkModule(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
foreach (['http', 'database', 'view', 'auth', 'windows', 'wordpress'] as $component) {
    checkModule(!Composer\InstalledVersions::isInstalled('fxfavalessa/fx-' . $component), 'Dependencia inesperada: ' . $component);
}
checkModule(!class_exists(Illuminate\Http\Request::class) && !class_exists(Smarty::class), 'Camada opcional inesperada.');
$root = sys_get_temp_dir() . '/fx-module-isolated-' . bin2hex(random_bytes(8));
mkdir($root . '/config', 0775, true);
mkdir($root . '/modules/hello', 0775, true);
copy(__DIR__ . '/config/modules.json', $root . '/config/modules.json');
copy(__DIR__ . '/modules/hello/fx-module.json', $root . '/modules/hello/fx-module.json');
copy(__DIR__ . '/modules/hello/help.html', $root . '/modules/hello/help.html');
try {
    $manager = new Fx\Framework\Modules\ModuleManager($root);
    checkModule($manager->doctor() === [], 'Modulo ativado implicitamente.');
    $manager->enable('hello');
    $app = new Fx\Framework\Foundation\CoreApplication($root);
    $manager->register($app);
    $app->boot();
    checkModule($app->make('hello.message') === 'Modulo hello carregado.', 'Provider indisponivel.');
    $artisan = new Fx\Framework\Console\Artisan($root);
    checkModule($artisan->has('module:enable') && !$artisan->has('route:list'), 'Comandos incorretos.');
    $manager->disable('hello');
    $nextApp = new Fx\Framework\Foundation\CoreApplication($root);
    $manager->register($nextApp);
    checkModule(!$nextApp->bound('hello.message'), 'Modulo desativado carregou novamente.');
    checkModule($app->bound('hello.message'), 'Estado de processo existente mudou indevidamente.');
    echo "modules: instalacao isolada OK\n";
} finally {
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
    rmdir($root);
}
