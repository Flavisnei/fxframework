<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeMiddlewareCommand extends Command
{
    protected static $defaultName = 'make:middleware';
    protected static $defaultDescription = 'Cria uma classe de middleware';

    public function __construct(private readonly string $root) { parent::__construct(); $this->addArgument('name', InputArgument::REQUIRED); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = ucfirst((string) $input->getArgument('name'));
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) { $output->writeln('<error>Nome de classe invalido.</error>'); return Command::INVALID; }
        if (!str_ends_with($name, 'Middleware')) { $name .= 'Middleware'; }
        $directory = $this->root . '/app/Middleware'; $file = $directory . '/' . $name . '.php';
        if (is_file($file)) { $output->writeln('<error>O arquivo ja existe.</error>'); return Command::FAILURE; }
        if (!is_dir($directory)) { mkdir($directory, 0775, true); }
        $template = str_replace('{{name}}', $name, <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Middleware;

use Fx\Framework\Http\Request;
use Fx\Framework\Middleware\Middleware;

final class {{name}} implements Middleware
{
    public function process(Request $request, callable $next): mixed
    {
        return $next($request);
    }
}
PHP);
        file_put_contents($file, $template, LOCK_EX);
        $output->writeln("<info>Created:</info> {$file}");
        return Command::SUCCESS;
    }
}
