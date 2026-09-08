<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Commands;

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MigrationStatusCommand extends Command
{
    protected static $defaultName = 'migrate:status';
    protected static $defaultDescription = 'Exibe o status das migrations';

    public function __construct(private readonly string $root) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        require $this->root . '/bootstrap/app.php';
        $connection = Capsule::connection();
        $ran = $connection->getSchemaBuilder()->hasTable('migrations')
            ? $connection->table('migrations')->pluck('batch', 'migration')->all() : [];
        $rows = [];
        foreach (glob($this->root . '/database/migrations/*.php') ?: [] as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $rows[] = [isset($ran[$name]) ? 'Ran' : 'Pending', $name, $ran[$name] ?? '-'];
        }
        (new Table($output))->setHeaders(['Status', 'Migration', 'Batch'])->setRows($rows)->render();
        return Command::SUCCESS;
    }
}
