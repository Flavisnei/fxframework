<?php
declare(strict_types=1);
namespace Fx\Framework\Admin;

use Fx\Framework\Foundation\Application;
use Fx\Framework\Routing\Router;
use Fx\Framework\Support\ServiceProvider;

final class AdminProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!$this->app instanceof Application || !$this->app->bound(Panel::class)) { throw new \RuntimeException('Configure Panel no container de uma Application HTTP antes de ativar fx-admin.'); }
        $this->app->make(Panel::class)->mount($this->app->make(Router::class));
    }
}
