<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$app = new Fx\Framework\Foundation\CoreApplication(__DIR__);
$modules = new Fx\Framework\Modules\ModuleManager(__DIR__);
$modules->register($app);
$app->boot();
echo $app->bound('hello.message') ? $app->make('hello.message') . PHP_EOL : "Modulo hello inativo.\n";
