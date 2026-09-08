<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Commands;

use Fx\Framework\Console\Application as LegacyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class LegacyCommand extends Command
{
    public function __construct(string $name, string $description, private readonly bool $requiresName = false)
    {
        parent::__construct($name);
        $this->setDescription($description);
        if ($requiresName) { $this->addArgument('name', InputArgument::REQUIRED, 'Nome do artefato'); }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $arguments = ['fxartisan', (string) $this->getName()];
        if ($this->requiresName) { $arguments[] = (string) $input->getArgument('name'); }
        return (new LegacyApplication())->run($arguments);
    }
}
