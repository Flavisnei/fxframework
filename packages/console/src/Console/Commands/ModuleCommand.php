<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Commands;

use Fx\Framework\Modules\ModuleManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ModuleCommand extends Command
{
    public function __construct(private readonly string $root, private readonly string $operation)
    {
        parent::__construct('module:' . $operation);
    }

    protected function configure(): void
    {
        $descriptions = ['status' => 'Lista modulos registrados e seu estado', 'doctor' => 'Valida dependencias e providers dos modulos ativos', 'enable' => 'Ativa um modulo e suas dependencias registradas', 'disable' => 'Desativa modulo sem apagar dados', 'refresh' => 'Valida e reconhece novas versoes dos modulos ativos'];
        $this->setDescription($descriptions[$this->operation]);
        if (in_array($this->operation, ['enable', 'disable'], true)) { $this->addArgument('id', InputArgument::REQUIRED, 'Identificador do manifesto fx-module.json'); }
        $this->setHelp('Usa config/modules.json e storage/framework/modules.json. Mudancas valem no proximo bootstrap; reinicie workers persistentes. Nao executa migrations, publica assets ou concede permissoes. module:refresh nao baixa pacotes. Manual: vendor/fxfavalessa/fx-modules/docs/index.html.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $manager = new ModuleManager($this->root);
        if ($this->operation === 'status') {
            $enabled = $manager->enabled();
            $definitions = $manager->definitions();
            $table = (new Table($output))->setHeaders(['Modulo', 'Versao disponivel', 'Estado / versao reconhecida']);
            foreach ($definitions as $id => $definition) { $table->addRow([$id, $definition->version, isset($enabled[$id]) ? 'ativo / ' . $enabled[$id] : 'inativo']); }
            foreach (array_diff_key($enabled, $definitions) as $id => $version) { $table->addRow([$id, 'ausente', 'ativo / ' . $version]); }
            $table->render();
            $output->writeln('Use module:doctor para validar o conjunto ativo.');
        } elseif ($this->operation === 'doctor') {
            $order = $manager->doctor();
            $output->writeln('Conjunto ativo valido: ' . implode(', ', array_map(fn ($definition) => $definition->id, $order)));
        } else {
            $enabled = match ($this->operation) {
                'enable' => $manager->enable((string) $input->getArgument('id')),
                'disable' => $manager->disable((string) $input->getArgument('id')),
                'refresh' => $manager->refresh(),
            };
            $output->writeln('Estado salvo. Modulos ativos: ' . implode(', ', array_keys($enabled)));
        }
        return self::SUCCESS;
    }
}
