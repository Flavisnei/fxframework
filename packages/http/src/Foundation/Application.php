<?php

declare(strict_types=1);

namespace Fx\Framework\Foundation;

use Fx\Framework\Http\ExceptionHandler;
use Fx\Framework\Http\Kernel;
use Fx\Framework\Http\Request;
use Fx\Framework\Routing\Router;

final class Application extends CoreApplication
{
    public function __construct(string $basePath)
    {
        parent::__construct($basePath, true);
        $this->singleton(Router::class, fn (): Router => new Router($this));
        $this->instance(ExceptionHandler::class, new ExceptionHandler(false));
        $this->singleton(Kernel::class);
    }

    public function setRequest(Request $request): void
    {
        $this->instance(Request::class, $request);
        $this->instance(\Illuminate\Http\Request::class, $request);
        $this->instance('request', $request);
    }

    public function withDebug(bool $debug): self
    {
        $this->instance(ExceptionHandler::class, new ExceptionHandler($debug));
        $this->forgetInstance(Kernel::class);
        return $this;
    }

}
