<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';
$app = new Fx\Framework\Foundation\Application(__DIR__);
$app->instance(Fx\Framework\Admin\Panel::class, Fx\Framework\Admin\AdminConfig::panel(__DIR__));
(new Fx\Framework\Modules\ModuleManager(__DIR__))->register($app);
$app->boot();
return $app;
