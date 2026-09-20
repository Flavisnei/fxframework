<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
$app = new Fx\Framework\Foundation\Application($root);
if (class_exists(Fx\Framework\Database\Database::class) && is_file($root . '/config/database.php')) {
    $connection = require $root . '/config/database.php';
    if ($connection !== null) Fx\Framework\Database\Database::boot($connection);
}
if (class_exists(Fx\Framework\View\View::class)) {
    $app->singleton(Fx\Framework\View\View::class, fn () => new Fx\Framework\View\View(
        $root . '/resources/views', $root . '/storage/views', $root . '/storage/cache'
    ));
}
$app->singleton(App\Services\Page::class, fn () => new App\Services\Page($root . '/resources/views'));
$router = $app->make(Fx\Framework\Routing\Router::class);
require $root . '/routes/web.php';
require $root . '/routes/api.php';
foreach (glob($root . '/routes/generated/*.php') ?: [] as $route) require $route;
foreach ($router->routes() as $route) $route->middleware(Fx\Framework\Middleware\VerifyCsrfToken::class);
if (class_exists(Fx\Framework\Admin\AdminConfig::class) && is_file($root . '/config/admin.php')) {
    $app->instance(Fx\Framework\Admin\Panel::class, Fx\Framework\Admin\AdminConfig::panel($root));
}
if (class_exists(Fx\Framework\Modules\ModuleManager::class) && is_file($root . '/config/modules.json')) {
    (new Fx\Framework\Modules\ModuleManager($root))->register($app);
}
$app->boot();
return $app;
