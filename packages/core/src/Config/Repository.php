<?php

declare(strict_types=1);

namespace Fx\Framework\Config;

/** Configuracao em memoria, sem leitura implicita de arquivos ou variaveis globais. */
final class Repository
{
    public function __construct(private array $items = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) { return $default; }
            $value = $value[$segment];
        }
        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $target = &$this->items;
        $segments = explode('.', $key);
        $last = array_pop($segments);
        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) { $target[$segment] = []; }
            $target = &$target[$segment];
        }
        $target[$last] = $value;
    }

    public function all(): array { return $this->items; }
}
