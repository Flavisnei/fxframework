<?php

declare(strict_types=1);

namespace Fx\Framework\Modules;

use Fx\Framework\Foundation\CoreApplication;
use Fx\Framework\Support\ServiceProvider;
use RuntimeException;

final class ModuleManager
{
    private readonly ModuleState $state;

    public function __construct(private readonly string $root)
    {
        if (!is_dir($root)) { throw new RuntimeException('Raiz da aplicacao inexistente.'); }
        $this->state = new ModuleState($root . '/storage/framework/modules.json');
    }

    /** @return array<string, ModuleDefinition> */
    public function definitions(): array
    {
        $file = $this->root . '/config/modules.json';
        if (!file_exists($file)) { return []; }
        $config = ModuleDefinition::readJson($file);
        if (($config['schema'] ?? null) !== 1 || !isset($config['manifests']) || !is_array($config['manifests']) || !array_is_list($config['manifests']) || array_diff(array_keys($config), ['schema', 'manifests'])) { throw new RuntimeException('config/modules.json deve conter schema 1 e lista manifests.'); }
        $definitions = [];
        $providers = [];
        foreach ($config['manifests'] as $path) {
            if (!is_string($path) || $path === '' || str_contains($path, "\0") || preg_match('~^(?:[/\\\\]|[a-zA-Z]:|[a-zA-Z]+://)~', $path) || in_array('..', explode('/', str_replace('\\', '/', $path)), true)) { throw new RuntimeException('Manifesto deve ter caminho relativo dentro da aplicacao.'); }
            // Repositorios Composer path podem usar symlink; a lista explicita autoriza esse destino.
            $definition = ModuleDefinition::fromFile($this->root . '/' . $path);
            if (isset($definitions[$definition->id])) { throw new RuntimeException("Modulo duplicado: {$definition->id}"); }
            if (isset($providers[strtolower($definition->provider)])) { throw new RuntimeException("Provider compartilhado por modulos distintos: {$definition->provider}"); }
            $definitions[$definition->id] = $definition;
            $providers[strtolower($definition->provider)] = true;
        }
        return $definitions;
    }

    public function enabled(): array { return $this->state->read(); }

    /** Valida o conjunto ativo e devolve as definicoes na ordem de dependencias. */
    public function doctor(): array
    {
        return $this->resolve($this->definitions(), $this->enabled());
    }

    /** Ativa tambem dependencias declaradas; nenhum provider e instanciado. */
    public function enable(string $id): array
    {
        return $this->state->change(function (array $enabled) use ($id): array {
            $definitions = $this->definitions();
            $order = $this->order($definitions, [$id]);
            foreach ($order as $definition) { $enabled[$definition->id] ??= $definition->version; }
            // Permite preparar novas dependencias de uma atualizacao sem reconhecer
            // silenciosamente a versao nova dos modulos que ja estavam ativos.
            $this->resolve($definitions, $enabled, false);
            return $enabled;
        });
    }

    public function disable(string $id): array
    {
        return $this->state->change(function (array $enabled) use ($id): array {
            if (!array_key_exists($id, $enabled)) { throw new RuntimeException("Modulo nao esta ativo: {$id}"); }
            $definitions = $this->definitions();
            foreach (array_keys($enabled) as $other) {
                if ($other !== $id && isset($definitions[$other]) && in_array($id, $definitions[$other]->requires, true)) { throw new RuntimeException("Nao pode desativar {$id}: {$other} depende dele."); }
            }
            unset($enabled[$id]);
            // Permite retirar um modulo cujo pacote foi removido ou cuja versao mudou.
            $this->resolve($definitions, $enabled);
            return $enabled;
        });
    }

    /** Reconhece as versoes apos atualizacao externa pelo Composer. Nao baixa codigo. */
    public function refresh(): array
    {
        return $this->state->change(function (array $enabled): array {
            $definitions = $this->definitions();
            $order = $this->resolve($definitions, $enabled, false);
            foreach ($order as $definition) { $enabled[$definition->id] = $definition->version; }
            return $enabled;
        });
    }

    /** Chamar no bootstrap antes de CoreApplication::boot(). */
    public function register(CoreApplication $app): void
    {
        if ($app->isBooted()) { throw new RuntimeException('Registre os modulos antes de boot().'); }
        $order = $this->doctor(); // Preflight de todo o conjunto antes de executar providers.
        foreach ($order as $definition) { $app->register($definition->provider); }
    }

    private function resolve(array $definitions, array $enabled, bool $checkVersions = true): array
    {
        $order = $this->order($definitions, array_keys($enabled));
        foreach ($order as $definition) {
            if (!array_key_exists($definition->id, $enabled)) { throw new RuntimeException("Dependencia desativada: {$definition->id}"); }
            if ($checkVersions && $enabled[$definition->id] !== $definition->version) { throw new RuntimeException("Versao alterada: {$definition->id}. Revise a atualizacao e execute module:refresh."); }
            if (!is_subclass_of($definition->provider, ServiceProvider::class) || !(new \ReflectionClass($definition->provider))->isInstantiable()) { throw new RuntimeException("Provider ausente ou invalido: {$definition->provider}. Confira o autoload Composer."); }
        }
        return $order;
    }

    private function order(array $definitions, array $ids): array
    {
        $visiting = [];
        $visited = [];
        $order = [];
        $visit = function (string $id) use (&$visit, &$visiting, &$visited, &$order, $definitions): void {
            if (isset($visited[$id])) { return; }
            if (isset($visiting[$id])) { throw new RuntimeException('Dependencia circular: ' . implode(' -> ', [...array_keys($visiting), $id])); }
            if (!isset($definitions[$id])) { throw new RuntimeException("Modulo ou dependencia nao registrado: {$id}"); }
            $visiting[$id] = true;
            foreach ($definitions[$id]->requires as $dependency) { $visit($dependency); }
            unset($visiting[$id]);
            $visited[$id] = true;
            $order[] = $definitions[$id];
        };
        foreach ($ids as $id) { $visit($id); }
        return $order;
    }
}
