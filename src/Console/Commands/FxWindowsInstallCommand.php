<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Commands;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class FxWindowsInstallCommand extends Command
{
    protected static $defaultName = 'fxwindows:install';
    protected static $defaultDescription = 'Publica os assets do FX Windows na aplicacao';

    public function __construct(private readonly string $root)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = dirname(__DIR__, 3) . '/resources/fxwindows';
        $destination = rtrim($this->root, '/\\') . '/public/assets/fxwindows';

        if (!is_dir($source)) {
            throw new RuntimeException("Assets do FX Windows nao encontrados: {$source}");
        }

        if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
            throw new RuntimeException("Nao foi possivel criar: {$destination}");
        }

        foreach (['fxwindows.js', 'fxwindows.css'] as $file) {
            if (!copy($source . '/' . $file, $destination . '/' . $file)) {
                throw new RuntimeException("Nao foi possivel publicar: {$file}");
            }
        }

        $output->writeln("<info>FX Windows instalado:</info> {$destination}");
        return Command::SUCCESS;
    }
}
