<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Commands;

use Fx\Framework\Console\Installation\ComposerInstaller;
use Fx\Framework\Console\Installation\PackageCatalog;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PackageInstallCommand extends Command
{
    public function __construct(private readonly string $root, private readonly bool $preset = false)
    {
        parent::__construct($preset ? 'preset:install' : 'module:install');
    }

    protected function configure(): void
    {
        $this->setDescription($this->preset ? 'Adiciona os pacotes de um preset via Composer' : 'Instala um componente do catalogo via Composer')
            ->addArgument('name', InputArgument::REQUIRED, $this->preset ? 'minimal, api, wordpress ou admin' : 'Componente listado em module:list')
            ->addOption('constraint', null, InputOption::VALUE_REQUIRED, 'Restricao Composer; use @dev somente no desenvolvimento local', '^1.0')
            ->addOption('composer', null, InputOption::VALUE_REQUIRED, 'Caminho do composer.phar ou executavel Unix')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Resolve dependencias sem instalar ou alterar manifesto/lock')
            ->setHelp('Execute na raiz de um projeto com composer.json. Presets adicionam pacotes e preservam os requisitos existentes. Scripts e plugins Composer ficam desabilitados. Nao gera aplicacao, ativa providers, publica assets ou executa migrations. Ajuda: packages/console/docs/index.html ou vendor/fxfavalessa/fx-console/docs/index.html.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $packages = $this->preset ? PackageCatalog::preset($name) : [PackageCatalog::package($name)];
        return (new ComposerInstaller($this->root))->install($packages, (string) $input->getOption('constraint'), $input->getOption('composer'), (bool) $input->getOption('dry-run'), $output);
    }
}
