<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Commands;

use Fx\Framework\Console\Application as LegacyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'about', description: 'Exibe informacoes sobre a aplicacao e o ambiente')]
final class AboutCommand extends Command
{

    public function __construct(private readonly string $root) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new Table($output))->setRows([
            ['FX Framework', LegacyApplication::VERSION],
            ['PHP', PHP_VERSION],
            ['Ambiente', getenv('APP_ENV') ?: 'local'],
            ['Debug', filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL) ? 'true' : 'false'],
            ['Raiz', $this->root],
        ])->render();
        return Command::SUCCESS;
    }
}
