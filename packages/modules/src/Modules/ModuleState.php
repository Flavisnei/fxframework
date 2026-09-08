<?php

declare(strict_types=1);

namespace Fx\Framework\Modules;

use RuntimeException;

/** Estado separado do codigo; alteracoes serializadas e substituicao atomica. */
final class ModuleState
{
    public function __construct(private readonly string $file) {}

    public function read(): array
    {
        if (!file_exists($this->file)) { return []; }
        $data = ModuleDefinition::readJson($this->file);
        if (($data['schema'] ?? null) !== 1 || !isset($data['enabled']) || !is_array($data['enabled']) || array_diff(array_keys($data), ['schema', 'enabled'])) { throw new RuntimeException('Estado de modulos invalido; restaure uma copia valida.'); }
        foreach ($data['enabled'] as $id => $version) {
            if (!ModuleDefinition::validId($id) || !is_string($version) || $version === '') { throw new RuntimeException('Estado de modulos contem entrada invalida.'); }
        }
        return $data['enabled'];
    }

    public function change(callable $transform): array
    {
        $directory = dirname($this->file);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) { throw new RuntimeException('Nao foi possivel criar diretorio de estado.'); }
        $lock = fopen($this->file . '.lock', 'c+b');
        if ($lock === false) { throw new RuntimeException('Nao foi possivel abrir lock dos modulos.'); }
        $temporary = false;
        try {
            if (!flock($lock, LOCK_EX)) { throw new RuntimeException('Nao foi possivel bloquear estado dos modulos.'); }
            $current = $this->read();
            $next = $transform($current);
            if ($next === $current) { return $next; }
            $json = json_encode(['schema' => 1, 'enabled' => (object) $next], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $temporary = tempnam($directory, '.fx-modules-');
            if ($temporary === false || file_put_contents($temporary, $json) !== strlen($json) || !chmod($temporary, 0660) || !rename($temporary, $this->file)) { throw new RuntimeException('Falha ao salvar estado dos modulos. O estado anterior foi preservado.'); }
            return $next;
        } finally {
            if ($temporary !== false && is_file($temporary)) { unlink($temporary); }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
