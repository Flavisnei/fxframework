<?php
declare(strict_types=1);
namespace Fx\Framework\Console\Commands;

use Fx\Framework\Admin\AdminConfig;
use Fx\Framework\Admin\Mail\MailConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class AdminMailCommand extends Command
{
    public function __construct(private readonly string $root) { parent::__construct('admin:mail'); }
    protected function configure(): void
    {
        $this->setDescription('Inicializa, consulta ou processa a fila de recuperacao SMTP')
            ->addOption('init', null, InputOption::VALUE_NONE, 'Cria somente a tabela da fila, sem enviar')
            ->addOption('status', null, InputOption::VALUE_NONE, 'Mostra contagens, sem enviar')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Quantidade maxima de mensagens por execucao', '20');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('init') && $input->getOption('status')) { throw new \RuntimeException('Escolha --init ou --status.'); }
        $config = AdminConfig::read($this->root); $queue = MailConfig::queue($config);
        if ($input->getOption('init')) { $queue->install(); $output->writeln('Fila inicializada; nenhuma mensagem enviada.'); return self::SUCCESS; }
        $queue->assertReady();
        if ($input->getOption('status')) { $result = $queue->status(); }
        else {
            $limit = (string) $input->getOption('limit');
            if (!ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 100) { throw new \RuntimeException('--limit deve estar entre 1 e 100.'); }
            $result = $queue->work(MailConfig::sender($config), (int) $limit);
        }
        $output->writeln(json_encode($result, JSON_THROW_ON_ERROR));
        return ($result['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
