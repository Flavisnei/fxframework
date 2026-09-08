<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Commands;

use Fx\Framework\Foundation\Application;
use Fx\Framework\Routing\Router;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RouteListCommand extends Command
{
    protected static $defaultName = 'route:list';
    protected static $defaultDescription = 'Lista as rotas registradas';

    public function __construct(private readonly string $root) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $bootstrap = $this->root . '/bootstrap/app.php';
        if (!is_file($bootstrap)) { $output->writeln('<error>bootstrap/app.php nao encontrado.</error>'); return Command::FAILURE; }
        /** @var Application $app */ $app = require $bootstrap;
        $rows = [];
        foreach ($app->make(Router::class)->routes() as $route) {
            $action = is_array($route->action)
                ? (is_object($route->action[0]) ? $route->action[0]::class : $route->action[0]) . '@' . $route->action[1]
                : (is_string($route->action) ? $route->action : 'Closure');
            $rows[] = [implode('|', $route->methods), $route->uri, $action, implode(', ', array_map(fn ($m) => is_string($m) ? $m : $m::class, $route->middlewareStack()))];
        }
        (new Table($output))->setHeaders(['Method', 'URI', 'Action', 'Middleware'])->setRows($rows)->render();
        return Command::SUCCESS;
    }
}
