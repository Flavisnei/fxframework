<?php

declare(strict_types=1);

namespace Fx\Framework\Database;

use Illuminate\Database\Schema\Builder;

abstract class Migration
{
    abstract public function up(Builder $schema): void;
    abstract public function down(Builder $schema): void;
}
