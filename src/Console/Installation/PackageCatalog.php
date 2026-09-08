<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Installation;

use InvalidArgumentException;

/** Catalogo de componentes disponiveis; nao representa modulos ativos. */
final class PackageCatalog
{
    public const COMPONENTS = [
        'core' => 'Container, configuracao e providers',
        'http' => 'Rotas, requests, respostas e middleware',
        'database' => 'Banco, Eloquent e migrations',
        'view' => 'Templates Smarty',
        'console' => 'FX Artisan',
        'auth' => 'Primitivas de autenticacao; sem painel',
        'validation' => 'Validacao de dados',
        'windows' => 'Assets FX Windows; sem painel',
        'wordpress' => 'Adaptador para o WordPress hospedeiro',
        'modules' => 'Registro, dependencias e ativacao de modulos',
        'admin' => 'Painel FX Windows com usuarios, perfis e SQLite',
    ];

    public const PRESETS = [
        'minimal' => ['core'],
        'api' => ['http', 'console'],
        'wordpress' => ['wordpress'],
        'admin' => ['admin', 'console'],
    ];

    public static function package(string $component): string
    {
        $component = str_starts_with($component, 'fxfavalessa/fx-') ? substr($component, 15) : $component;
        if (!isset(self::COMPONENTS[$component])) {
            throw new InvalidArgumentException('Componente desconhecido. Consulte module:list.');
        }
        return 'fxfavalessa/fx-' . $component;
    }

    public static function preset(string $preset): array
    {
        if (!isset(self::PRESETS[$preset])) {
            throw new InvalidArgumentException('Preset indisponivel. Use minimal, api, wordpress ou admin.');
        }
        return array_map(self::package(...), self::PRESETS[$preset]);
    }
}
