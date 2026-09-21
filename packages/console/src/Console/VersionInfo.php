<?php
declare(strict_types=1);

namespace Fx\Framework\Console;

final class VersionInfo
{
    public const FRAMEWORK = '1.1.4';
    public const WORDPRESS = '1.1.4';
    public const ADMIN = '1.1.4';
    public const ARTISAN = Application::VERSION;

    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            'FX Framework' => self::FRAMEWORK,
            'FX WordPress' => self::WORDPRESS,
            'FX Admin' => self::ADMIN,
            'FX Artisan' => self::ARTISAN,
        ];
    }
}
