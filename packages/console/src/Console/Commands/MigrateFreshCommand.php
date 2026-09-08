<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Commands;

use Fx\Framework\Console\Application as LegacyApplication;
use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class MigrateFreshCommand extends Command
{
    protected static $defaultName = 'migrate:fresh';
    protected static $defaultDescription = 'Remove todas as tabelas e executa novamente as migrations';

    public function __construct(private readonly string $root)
    {
        parent::__construct();
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Executa sem confirmacao');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->getOption('force') && !$io->confirm('Todas as tabelas serao apagadas. Continuar?', false)) { return Command::SUCCESS; }
        require $this->root . '/bootstrap/app.php';
        Capsule::connection()->getSchemaBuilder()->dropAllTables();
        $io->success('Tabelas removidas.');
        return (new LegacyApplication())->run(['fxartisan', 'migrate']);
    }
}
