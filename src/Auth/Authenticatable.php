<?php

declare(strict_types=1);

namespace Fx\Framework\Auth;

interface Authenticatable
{
    public function getAuthIdentifier(): string|int;
}
