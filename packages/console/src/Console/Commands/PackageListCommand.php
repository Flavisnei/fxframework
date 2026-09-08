<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Commands;

use Fx\Framework\Console\Installation\PackageCatalog;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PackageListCommand extends Command
{
    protected static $defaultName = 'module:list';
    protected static $defaultDescription = 'Mostra o catalogo de componentes e presets disponiveis';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = (new Table($output))->setHeaders(['Componente', 'Pacote', 'Finalidade']);
        foreach (PackageCatalog::COMPONENTS as $name => $description) { $table->addRow([$name, PackageCatalog::package($name), $description]); }
        $table->render();
        $output->writeln('Presets (aditivos, sem remover dependencias existentes):');
        foreach (PackageCatalog::PRESETS as $name => $components) { $output->writeln($name . ': ' . implode(', ', $components)); }
        $output->writeln('Catalogo de pacotes, nao de modulos ativos. Admin requer configuracao e admin:init. Pacotes locais requerem repositorio path e --constraint=@dev.');
        return self::SUCCESS;
    }
}
