<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fx\Framework\Foundation\CoreApplication;
use Fx\Framework\Support\ServiceProvider;

final class ExampleProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('greeting', fn () => 'Ola, ' . $this->app->config()->get('app.name'));
    }
}

$app = new CoreApplication(__DIR__);
$app->config()->set('app.name', 'FX Core');
$app->registerProviders([ExampleProvider::class])->boot();
echo $app->make('greeting') . PHP_EOL;
