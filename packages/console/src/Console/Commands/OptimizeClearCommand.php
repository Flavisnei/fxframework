<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Commands;

use Fx\Framework\Console\Application as LegacyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'optimize:clear', description: 'Limpa todos os caches da aplicacao')]
final class OptimizeClearCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = new LegacyApplication();
        $cache = $app->run(['fxartisan', 'cache:clear']);
        $tmp = $app->run(['fxartisan', 'cache:cleartmp']);
        return max($cache, $tmp);
    }
}
