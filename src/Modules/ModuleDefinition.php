<?php

declare(strict_types=1);

namespace Fx\Framework\Modules;

use RuntimeException;

final class ModuleDefinition
{
    private function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly string $provider,
        public readonly array $requires,
        public readonly array $resources,
        public readonly array $permissions,
        public readonly string $directory,
    ) {}

    public static function validId(mixed $id): bool
    {
        return is_string($id) && strlen($id) <= 100 && preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z][a-z0-9-]*)*$/D', $id) === 1;
    }

    public static function readJson(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) { throw new RuntimeException("Arquivo ausente ou ilegivel: {$path}"); }
        $contents = file_get_contents($path);
        if ($contents === false) { throw new RuntimeException("Falha ao ler {$path}"); }
        try { $data = json_decode($contents, true, 64, JSON_THROW_ON_ERROR); }
        catch (\JsonException $exception) { throw new RuntimeException("JSON invalido em {$path}: " . $exception->getMessage(), 0, $exception); }
        if (!is_array($data)) { throw new RuntimeException("Esperado objeto JSON em {$path}"); }
        return $data;
    }

    public static function fromFile(string $file): self
    {
        $data = self::readJson($file);
        $allowed = ['schema', 'id', 'version', 'provider', 'requires', 'help', 'routes', 'migrations', 'assets', 'permissions'];
        if (array_diff(array_keys($data), $allowed) || ($data['schema'] ?? null) !== 1) { throw new RuntimeException("Schema ou campo desconhecido em {$file}"); }
        if (!self::validId($data['id'] ?? null)) { throw new RuntimeException("Identificador invalido em {$file}"); }
        $id = $data['id'];
        if (!is_string($data['version'] ?? null) || !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/D', $data['version'])) { throw new RuntimeException("Versao invalida: {$id}"); }
        if (!is_string($data['provider'] ?? null) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $data['provider'])) { throw new RuntimeException("Provider invalido: {$id}"); }
        $requires = $data['requires'] ?? [];
        $permissions = $data['permissions'] ?? [];
        foreach (['requires' => $requires, 'permissions' => $permissions] as $key => $values) {
            if (!is_array($values) || !array_is_list($values)) { throw new RuntimeException("{$key} deve ser lista: {$id}"); }
            foreach ($values as $value) {
                if (!self::validId($value)) { throw new RuntimeException("{$key} contem identificador invalido: {$id}"); }
            }
            if (count(array_unique($values)) !== count($values)) { throw new RuntimeException("{$key} contem duplicatas: {$id}"); }
        }
        $directory = realpath(dirname($file));
        if ($directory === false) { throw new RuntimeException("Diretorio ausente: {$id}"); }
        $resources = ['help' => self::resource($directory, $data['help'] ?? null, false)];
        if (strtolower(pathinfo($resources['help'], PATHINFO_EXTENSION)) !== 'html') { throw new RuntimeException("Ajuda deve ser HTML: {$id}"); }
        foreach (['assets', 'migrations'] as $key) {
            if (isset($data[$key])) { $resources[$key] = self::resource($directory, $data[$key], true); }
        }
        $routes = $data['routes'] ?? [];
        if (!is_array($routes)) { throw new RuntimeException("routes deve ser objeto por contexto: {$id}"); }
        foreach ($routes as $context => $path) {
            if (!in_array($context, ['http', 'wordpress', 'console'], true)) { throw new RuntimeException("Contexto de rotas desconhecido: {$id}"); }
            $resources['routes'][$context] = self::resource($directory, $path, false);
        }
        return new self($id, $data['version'], $data['provider'], $requires, $resources, $permissions, $directory);
    }

    private static function resource(string $directory, mixed $relative, bool $isDirectory): string
    {
        if (!is_string($relative) || $relative === '' || str_contains($relative, "\0") || preg_match('~^(?:[/\\\\]|[a-zA-Z]:|[a-zA-Z]+://)~', $relative)) { throw new RuntimeException('Recurso deve usar caminho relativo ao modulo.'); }
        $resolved = realpath($directory . '/' . $relative);
        $base = str_replace('\\', '/', $directory) . '/';
        $candidate = $resolved === false ? '' : str_replace('\\', '/', $resolved);
        if (PHP_OS_FAMILY === 'Windows') { $base = strtolower($base); $candidate = strtolower($candidate); }
        if (!str_starts_with($candidate, $base) || !($isDirectory ? is_dir($resolved) : is_file($resolved))) { throw new RuntimeException("Recurso ausente ou fora do modulo: {$relative}"); }
        return $resolved;
    }
}
