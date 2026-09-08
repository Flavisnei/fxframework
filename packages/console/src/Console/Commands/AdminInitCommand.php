<?php
declare(strict_types=1);
namespace Fx\Framework\Console\Commands;

use Fx\Framework\Admin\AdminConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final class AdminInitCommand extends Command
{
    public function __construct(private readonly string $root) { parent::__construct('admin:init'); }
    protected function configure(): void
    {
        $this->setDescription('Cria as tabelas SQLite e o primeiro administrador, sem substituir contas existentes')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email do primeiro administrador')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nome', 'Administrador')
            ->addOption('password-env', null, InputOption::VALUE_REQUIRED, 'Nome de variavel de ambiente contendo a senha; opcional em terminal interativo');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getOption('email');
        if (!is_string($email) || $email === '') { throw new \RuntimeException('Informe --email.'); }
        $env = $input->getOption('password-env');
        if ($env !== null) { $password = getenv($env); }
        else {
            if (!$input->isInteractive()) { throw new \RuntimeException('Use --password-env em execucao nao interativa.'); }
            $question = (new Question('Senha inicial (12 a 72 bytes): '))->setHidden(true)->setHiddenFallback(false);
            $password = $this->getHelper('question')->ask($input, $output, $question);
        }
        if (!is_string($password)) { throw new \RuntimeException('Senha inicial indisponivel.'); }
        $config = AdminConfig::read($this->root);
        AdminConfig::store($config, true)->install((string) $input->getOption('name'), $email, $password);
        $output->writeln('Admin inicializado. Configure o bootstrap e ative fx-admin conforme o manual. Nenhuma senha foi exibida.');
        return self::SUCCESS;
    }
}
