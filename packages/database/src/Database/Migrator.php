<?php

declare(strict_types=1);

namespace Fx\Framework\Database;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;

final class Migrator
{
    public function __construct(private readonly ConnectionInterface $connection, private readonly string $path)
    {
    }

    /** @return list<string> */
    public function migrate(): array
    {
        $this->repository();
        $ran = $this->connection->table('migrations')->pluck('migration')->all();
        $batch = (int) $this->connection->table('migrations')->max('batch') + 1;
        $executed = [];
        foreach ($this->files() as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (in_array($name, $ran, true)) { continue; }
            $migration = require $file;
            if (!$migration instanceof Migration) { throw new RuntimeException("Migration invalida: {$file}"); }
            // MySQL executa commit implicito em comandos DDL; envolver a alteracao
            // de schema em transaction causa "There is no active transaction".
            $migration->up($this->connection->getSchemaBuilder());
            $this->connection->table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
            $executed[] = $name;
        }
        return $executed;
    }

    /** @return list<string> */
    public function rollback(): array
    {
        $this->repository();
        $batch = (int) $this->connection->table('migrations')->max('batch');
        if ($batch === 0) { return []; }
        $rows = $this->connection->table('migrations')->where('batch', $batch)->orderByDesc('id')->get();
        $rolledBack = [];
        foreach ($rows as $row) {
            $file = $this->path . DIRECTORY_SEPARATOR . $row->migration . '.php';
            if (!is_file($file)) { throw new RuntimeException("Migration ausente: {$file}"); }
            $migration = require $file;
            $migration->down($this->connection->getSchemaBuilder());
            $this->connection->table('migrations')->where('id', $row->id)->delete();
            $rolledBack[] = $row->migration;
        }
        return $rolledBack;
    }

    private function repository(): void
    {
        $schema = $this->connection->getSchemaBuilder();
        if (!$schema->hasTable('migrations')) {
            $schema->create('migrations', function ($table): void {
                $table->increments('id'); $table->string('migration')->unique(); $table->unsignedInteger('batch');
            });
        }
    }

    /** @return list<string> */
    private function files(): array
    {
        $files = glob(rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);
        return $files;
    }
}
