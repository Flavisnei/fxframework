<?php

declare(strict_types=1);

namespace Fx\Framework\Windows;

final class Assets
{
    public static function directory(): string
    {
        return dirname(__DIR__, 2) . '/resources/fxwindows';
    }
}
