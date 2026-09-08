<?php

declare(strict_types=1);

namespace Fx\Framework\Database;

use Illuminate\Database\Capsule\Manager as Capsule;

final class Database
{
    public static function boot(array $connection): Capsule
    {
        $capsule = new Capsule();
        $capsule->addConnection($connection);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return $capsule;
    }
}
