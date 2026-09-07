<?php

declare(strict_types=1);

// Usa exclusivamente o autoload desta instalacao, nunca o vendor do framework.
require __DIR__ . '/vendor/autoload.php';

use Composer\InstalledVersions;
use Fx\Framework\Foundation\CoreApplication;
use Fx\Framework\Config\Repository;
use Fx\Framework\Support\ServiceProvider;

function check(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$allowed = ['fxfavalessa/example-minimal', 'fxfavalessa/fx-core', 'illuminate/container',
    'illuminate/contracts', 'psr/container', 'psr/simple-cache'];
$installed = array_values(array_filter(InstalledVersions::getInstalledPackages(),
    static fn (string $package): bool => InstalledVersions::getInstallPath($package) !== null));
check(array_diff($installed, $allowed) === [], 'Dependencias inesperadas no Core.');
check(!class_exists(\Fx\Framework\Foundation\Application::class), 'Application web instalada no Core.');
check(!class_exists(\Smarty::class), 'Smarty instalado no Core.');
check(!class_exists(\Illuminate\Database\Capsule\Manager::class), 'Banco instalado no Core.');
check(!class_exists(\Symfony\Component\HttpFoundation\Request::class), 'HTTP instalado no Core.');

$sessionStatus = session_status();
$host = \Illuminate\Container\Container::getInstance();
$app = new CoreApplication(__DIR__);
$app->config()->set('app.name', 'minimal');
$app->register(new class($app) extends ServiceProvider {
    public function register(): void { $this->app->bind('answer', fn () => 42); }
    public function boot(Repository $config): void { $config->set('booted', true); }
});
$app->boot();
check($app->make('answer') === 42, 'Binding nao resolvido.');
check($app->config()->get('booted') === true, 'Provider nao inicializado.');
check($app->call(fn (Repository $config) => $config->get('app.name')) === 'minimal', 'Injecao nao funciona.');
check(session_status() === $sessionStatus, 'Core alterou a sessao.');
check(\Illuminate\Container\Container::getInstance() === $host, 'Core substituiu o container hospedeiro.');
echo 'Core independente OK: ' . implode(', ', $installed) . PHP_EOL;
