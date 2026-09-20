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
            ->addOption('watch', null, InputOption::VALUE_NONE, 'Processa continuamente; rele .env a cada ciclo; encerre com Ctrl+C')
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Segundos entre ciclos no modo watch (1 a 60)', '10')
            ->addOption('cycles', null, InputOption::VALUE_REQUIRED, 'Limite de ciclos do watch; 0 executa continuamente', '0')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Quantidade maxima de mensagens por execucao', '20');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('init') && $input->getOption('status')) { throw new \RuntimeException('Escolha --init ou --status.'); }
        if ($input->getOption('watch')) {
            if ($input->getOption('init') || $input->getOption('status')) { throw new \RuntimeException('--watch nao pode ser combinado com --init ou --status.'); }
            $interval=(string)$input->getOption('interval');$cycles=(string)$input->getOption('cycles');$limit=(string)$input->getOption('limit');
            if(!ctype_digit($interval) || (int)$interval<1 || (int)$interval>60 || !ctype_digit($cycles) || strlen($cycles)>6 || !ctype_digit($limit) || (int)$limit<1 || (int)$limit>100)throw new \RuntimeException('Use intervalo de 1 a 60, ciclos de 0 a 999999 e limite de 1 a 100.');
            $failed=false;
            for($cycle=0;(int)$cycles===0 || $cycle<(int)$cycles;$cycle++) {
                try {
                    $current=AdminConfig::read($this->root);
                    if(!isset($current['mail'])) { $result=['paused'=>true]; }
                    else {
                        $queue=MailConfig::queue($current);$queue->assertReady();
                        $result=$queue->work(MailConfig::sender($current),(int)$limit);
                        $failed=$failed || ($result['failed']??0)>0;
                    }
                    $output->writeln(json_encode($result,JSON_THROW_ON_ERROR));
                } catch(\Throwable) { $failed=true;$output->writeln('{"error":"Nao foi possivel processar a fila; confira configuracao e banco."}'); }
                if((int)$cycles===0 || $cycle+1<(int)$cycles)sleep((int)$interval);
            }
            return $failed ? self::FAILURE : self::SUCCESS;
        }
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
