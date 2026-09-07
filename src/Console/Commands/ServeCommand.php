<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ServeCommand extends Command
{
    protected static $defaultName = 'serve';
    protected static $defaultDescription = 'Inicia o servidor PHP de desenvolvimento';
    public function __construct(private readonly string $root) { parent::__construct(); $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host', '127.0.0.1')->addOption('port', null, InputOption::VALUE_REQUIRED, 'Porta', '8000'); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $address = $input->getOption('host') . ':' . $input->getOption('port');
        $output->writeln("<info>FX development server:</info> http://{$address}");
        $router = $this->root . '/server.php';
        $command = escapeshellarg(PHP_BINARY) . ' -S ' . escapeshellarg($address) . ' -t ' . escapeshellarg($this->root . '/public');
        if (is_file($router)) { $command .= ' ' . escapeshellarg($router); }
        passthru($command, $code);
        return $code;
    }
}
