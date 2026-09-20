<?php

declare(strict_types=1);

namespace Fx\Framework\Console;

use Fx\Framework\Console\Commands\AboutCommand;
use Fx\Framework\Console\Commands\FxWindowsInstallCommand;
use Fx\Framework\Console\Commands\LegacyCommand;
use Fx\Framework\Console\Commands\MakeMiddlewareCommand;
use Fx\Framework\Console\Commands\MigrateFreshCommand;
use Fx\Framework\Console\Commands\MigrationStatusCommand;
use Fx\Framework\Console\Commands\OptimizeClearCommand;
use Fx\Framework\Console\Commands\RouteListCommand;
use Fx\Framework\Console\Commands\ServeCommand;
use RuntimeException;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;

final class Artisan extends SymfonyApplication
{
    public function __construct(private readonly string $root)
    {
        parent::__construct('FX Artisan', Application::VERSION);
        $this->registerFrameworkCommands();
        $this->registerApplicationCommands();
        $this->addUsageHelp();
    }

    public static function fromCurrentDirectory(): self
    {
        $root = getcwd();
        if ($root === false) { throw new RuntimeException('Nao foi possivel identificar a raiz da aplicacao.'); }
        return new self($root);
    }

    private function registerFrameworkCommands(): void
    {
        $this->addCommands([
            new \Fx\Framework\Console\Commands\PackageListCommand(),
            new \Fx\Framework\Console\Commands\PackageInstallCommand($this->root),
            new \Fx\Framework\Console\Commands\PackageInstallCommand($this->root, true),
            new AboutCommand($this->root), new OptimizeClearCommand(),
            new \Fx\Framework\Console\Commands\SetupInitCommand(),
            new \Fx\Framework\Console\Commands\SetupUpgradeCommand($this->root),
            new LegacyCommand('make:controller', 'Cria um controller', true),
            new LegacyCommand('cache:clear', 'Limpa o cache da aplicacao'),
            new LegacyCommand('cache:cleartmp', 'Limpa arquivos temporarios'),
        ]);
        if (class_exists(\Fx\Framework\Admin\AdminConfig::class)) {
            $this->add(new \Fx\Framework\Console\Commands\AdminInitCommand($this->root));
            $this->add(new \Fx\Framework\Console\Commands\AdminMailCommand($this->root));
        }
        if (class_exists(\Fx\Framework\Modules\ModuleManager::class)) {
            foreach (['status', 'doctor', 'enable', 'disable', 'refresh'] as $operation) {
                $this->add(new \Fx\Framework\Console\Commands\ModuleCommand($this->root, $operation));
            }
        }
        $http = class_exists(\Fx\Framework\Foundation\Application::class);
        $database = class_exists(\Fx\Framework\Database\Database::class);
        if ($http) {
            $this->addCommands([
                new RouteListCommand($this->root), new MakeMiddlewareCommand($this->root), new ServeCommand($this->root),
                new LegacyCommand('app:init', 'Cria a estrutura inicial da aplicacao'),
                new LegacyCommand('make:request', 'Cria um request de validacao', true),
            ]);
        }
        if ($database) {
            $this->addCommands([
                new MigrationStatusCommand($this->root), new MigrateFreshCommand($this->root),
                new LegacyCommand('make:model', 'Cria um model Eloquent', true),
                new LegacyCommand('make:migration', 'Cria uma migration', true),
                new LegacyCommand('migrate', 'Executa migrations pendentes'),
                new LegacyCommand('migrate:rollback', 'Reverte o ultimo lote de migrations'),
            ]);
        }
        if ($http && $database && class_exists(\Fx\Framework\View\View::class)) {
            $this->add(new LegacyCommand('make:crud', 'Cria um CRUD completo', true));
        }
        if (class_exists(\Fx\Framework\Windows\Assets::class)) {
            $this->add(new FxWindowsInstallCommand($this->root));
        }
    }

    private function registerApplicationCommands(): void
    {
        $file = $this->root . '/app/Console/commands.php';
        if (!is_file($file)) { return; }
        $commands = require $file;
        if (!is_iterable($commands)) { throw new RuntimeException('app/Console/commands.php deve retornar comandos iteraveis.'); }
        foreach ($commands as $command) {
            if (!$command instanceof Command) { throw new RuntimeException('Comando personalizado invalido.'); }
            $this->add($command);
        }
    }

    private function addUsageHelp(): void
    {
        $examples = [
            'about' => 'fxartisan about', 'app:init' => 'fxartisan app:init',
            'cache:clear' => 'fxartisan cache:clear', 'cache:cleartmp' => 'fxartisan cache:cleartmp',
            'fxwindows:install' => 'fxartisan fxwindows:install',
            'make:controller' => 'fxartisan make:controller Cliente', 'make:crud' => 'fxartisan make:crud Cliente',
            'make:middleware' => 'fxartisan make:middleware Auth', 'make:migration' => 'fxartisan make:migration create_clientes_table',
            'make:model' => 'fxartisan make:model Cliente', 'make:request' => 'fxartisan make:request Cliente',
            'migrate' => 'fxartisan migrate', 'migrate:fresh' => 'fxartisan migrate:fresh',
            'migrate:rollback' => 'fxartisan migrate:rollback', 'migrate:status' => 'fxartisan migrate:status',
            'module:disable' => 'fxartisan module:disable exemplo', 'module:doctor' => 'fxartisan module:doctor',
            'module:enable' => 'fxartisan module:enable exemplo', 'module:install' => 'fxartisan module:install http',
            'module:list' => 'fxartisan module:list', 'module:refresh' => 'fxartisan module:refresh',
            'module:status' => 'fxartisan module:status', 'optimize:clear' => 'fxartisan optimize:clear',
            'preset:install' => 'fxartisan preset:install complete', 'route:list' => 'fxartisan route:list',
            'serve' => 'fxartisan serve --port=8080', 'setup:init' => 'fxartisan setup:init C:/Projetos/minha-app --profile=complete',
            'setup:upgrade' => 'fxartisan setup:upgrade --profile=complete --dry-run',
        ];

        foreach ($this->all() as $command) {
            $name = $command->getName();
            $example = $examples[$name] ?? 'fxartisan ' . $name;
            $command->setHelp("Uso:\n  {$example}\n\nExemplo:\n  {$example}\n\nConsulte as opcoes com:\n  {$example} --help");
        }
    }
}
