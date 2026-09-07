<?php

declare(strict_types=1);

namespace Fx\Framework\Container;

use Illuminate\Container\Container as IlluminateContainer;

/**
 * Service container com a API do Illuminate e autowiring por reflexao.
 *
 * Recursos herdados: bind, singleton, scoped, instance, alias, tag,
 * when/needs/give, make, makeWith e call.
 */
class Container extends IlluminateContainer
{
    public function __construct()
    {
        self::setInstance($this);
        $this->instance(self::class, $this);
        $this->instance(IlluminateContainer::class, $this);
    }
}
