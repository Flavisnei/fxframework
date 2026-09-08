<?php
declare(strict_types=1);
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
return [new class extends Command {
    protected function configure(): void { $this->setName('contacts:init')->setDescription('Cria a tabela de contatos sem apagar dados existentes.'); }
    protected function execute(InputInterface $input, OutputInterface $output): int {
        Example\Contacts\ContactStore::configured(dirname(__DIR__, 2), true)->install();
        $output->writeln('Tabela de contatos pronta. Dados existentes preservados.');
        return Command::SUCCESS;
    }
}];
