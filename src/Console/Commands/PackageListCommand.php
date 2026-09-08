<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Commands;

use Fx\Framework\Console\Installation\InstallCatalog;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'module:list', description: 'Mostra o catalogo de componentes e presets disponiveis')]
final class PackageListCommand extends Command
{

    protected function configure(): void
    {
        $this->addOption('catalog', null, InputOption::VALUE_REQUIRED, 'Arquivo JSON local ou URL HTTPS do catalogo')
            ->addOption('catalog-sha256', null, InputOption::VALUE_REQUIRED, 'SHA-256 esperado; obrigatorio para HTTPS');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $catalog = InstallCatalog::load($input->getOption('catalog'), $input->getOption('catalog-sha256'));
        $table = (new Table($output))->setHeaders(['Componente', 'Pacote', 'Finalidade']);
        foreach ($catalog->components as $name => $entry) { $table->addRow([$name, $entry['package'], $entry['description']]); }
        $table->render();
        $output->writeln('Presets (aditivos, sem remover dependencias existentes):');
        foreach ($catalog->presets as $name => $components) { $output->writeln($name . ': ' . implode(', ', $components)); }
        $output->writeln('Catalogo de pacotes, nao de modulos ativos. Admin requer configuracao e admin:init. Pacotes locais requerem repositorio path e --constraint=@dev.');
        return self::SUCCESS;
    }
}
