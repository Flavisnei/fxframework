<?php

declare(strict_types=1);

namespace Fx\Framework\Console;

use InvalidArgumentException;
use Fx\Framework\Database\Migrator;
use Illuminate\Database\Capsule\Manager as Capsule;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class Application
{
    public const VERSION = '0.2.0';

    private const LEGACY_COMMANDS = [
        'autoincrement', 'basetabelafx', 'crud', 'crudbasic', 'crudeloquent',
        'crudlite', 'delcrud', 'html', 'migration', 'router:make',
        'tabela:show', 'validated',
    ];

    public function run(array $arguments): int
    {
        $command = $arguments[1] ?? 'help';

        try {
            return match ($command) {
                'help', '-h', '--help', 'h' => $this->help(),
                'version', '-v', '--version', 'v' => $this->version(),
                'make:controller' => $this->makeClass($arguments[2] ?? '', 'Controllers', 'Controller'),
                'make:model', 'make:models' => $this->makeClass($arguments[2] ?? '', 'Models', 'Model'),
                'make:request' => $this->makeClass($arguments[2] ?? '', 'Requests', 'Request'),
                'make:migration' => $this->makeMigration($arguments[2] ?? ''),
                'make:crud' => $this->makeCrud($arguments[2] ?? ''),
                'app:init' => $this->initializeApplication(),
                'migrate' => $this->runMigrations(false),
                'migrate:rollback' => $this->runMigrations(true),
                'cache:clear' => $this->clearDirectory('storage/cache'),
                'cache:cleartmp' => $this->clearDirectory('storage/tmp'),
                default => $this->unknown($command),
            };
        } catch (Throwable $exception) {
            fwrite(STDERR, "Erro: {$exception->getMessage()}\n");
            return 1;
        }
    }

    private function help(): int
    {
        echo <<<'HELP'
FX Artisan

Uso:
  fxartisan <comando> [argumentos]

Comandos:
  help                         Exibe esta ajuda
  version                      Exibe a versao
  make:controller Nome         Cria app/Controllers/NomeController.php
  make:model Nome              Cria app/Models/Nome.php
  make:request Nome            Cria app/Requests/NomeRequest.php
  make:migration nome          Cria uma migration em database/migrations
  make:crud Nome               Cria model, request, controller, migration e views
  app:init                     Cria a estrutura inicial da aplicacao
  migrate                      Executa migrations pendentes
  migrate:rollback             Reverte o ultimo lote de migrations
  cache:clear                  Limpa storage/cache da aplicacao
  cache:cleartmp               Limpa storage/tmp da aplicacao

O diretorio de trabalho atual e considerado a raiz da aplicacao.
HELP;
        echo PHP_EOL;

        return 0;
    }

    private function version(): int
    {
        echo 'FX Artisan ' . self::VERSION . PHP_EOL;
        return 0;
    }

    private function makeClass(string $input, string $directory, string $suffix): int
    {
        $name = $this->normalizeClassName($input, $suffix);
        $root = $this->applicationRoot();
        $targetDirectory = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $directory;
        $target = $targetDirectory . DIRECTORY_SEPARATOR . $name . '.php';

        if (is_file($target)) {
            throw new RuntimeException("O arquivo ja existe: {$target}");
        }

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException("Nao foi possivel criar: {$targetDirectory}");
        }

        $namespace = 'App\\' . $directory;
        $body = $this->classTemplate($namespace, $name, $directory);

        if (file_put_contents($target, $body, LOCK_EX) === false) {
            throw new RuntimeException("Nao foi possivel gravar: {$target}");
        }

        echo "Criado: {$target}" . PHP_EOL;
        return 0;
    }

    private function normalizeClassName(string $input, string $suffix): string
    {
        $input = trim(str_replace(['/', '\\'], '', $input));
        if ($input === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $input) !== 1) {
            throw new InvalidArgumentException('Informe um nome de classe PHP valido.');
        }

        $name = ucfirst($input);
        if ($suffix !== 'Model' && !str_ends_with($name, $suffix)) {
            $name .= $suffix;
        }

        return $name;
    }

    private function initializeApplication(): int
    {
        $root = $this->applicationRoot();
        foreach (['app/Console', 'app/Controllers', 'app/Middleware', 'app/Models', 'app/Requests', 'bootstrap', 'config', 'database/migrations', 'public', 'resources/views', 'routes', 'storage/cache', 'storage/logs', 'storage/tmp'] as $directory) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);
            if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
                throw new RuntimeException("Nao foi possivel criar: {$path}");
            }
        }

        $this->writeNew($root . '/config/database.php', <<<'PHP'
<?php
return [
    'driver' => getenv('DB_DRIVER') ?: 'mysql',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_DATABASE') ?: '',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => getenv('DB_PREFIX') ?: '',
];
PHP);
        $this->writeNew($root . '/bootstrap/app.php', <<<'PHP'
<?php
use Fx\Framework\Database\Database;
use Fx\Framework\Foundation\Application;

$app = new Application(dirname(__DIR__));
$app->withDebug(filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL));
if (class_exists(Database::class)) {
    Database::boot(require $app->basePath('config/database.php'));
}
require $app->basePath('routes/web.php');
return $app;
PHP);
        $this->writeNew($root . '/routes/web.php', <<<'PHP'
<?php
use Fx\Framework\Routing\Router;
use Fx\Framework\Middleware\VerifyCsrfToken;

/** @var Router $router */
$router = $app->make(Router::class);
$router->middleware(VerifyCsrfToken::class);
$router->get('/', fn (): string => 'FX Framework');
foreach (glob(__DIR__ . '/generated/*.php') ?: [] as $routes) {
    require $routes;
}
PHP);
        $this->writeNew($root . '/public/index.php', <<<'PHP'
<?php
use Fx\Framework\Http\Kernel;

require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Kernel::class)->handle()->send();
PHP);
        $this->writeNew($root . '/fxartisan', <<<'PHP'
#!/usr/bin/env php
<?php

declare(strict_types=1);

use Fx\Framework\Console\Artisan;

require __DIR__ . '/vendor/autoload.php';
exit((new Artisan(__DIR__))->run());
PHP);
        $this->writeNew($root . '/server.php', <<<'PHP'
<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/public' . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/public/index.php';
PHP);
        foreach (['storage/cache/.gitkeep', 'storage/logs/.gitkeep', 'storage/tmp/.gitkeep'] as $file) {
            $this->writeNew($root . '/' . $file, '');
        }
        echo "Estrutura da aplicacao pronta em {$root}" . PHP_EOL;
        return 0;
    }

    private function makeMigration(string $input): int
    {
        $name = strtolower(trim($input));
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException('Informe um nome de migration em snake_case.');
        }
        $file = $this->applicationRoot() . '/database/migrations/' . date('Y_m_d_His') . '_' . $name . '.php';
        $this->writeNew($file, $this->migrationTemplate($this->tableFromMigration($name)));
        echo "Criado: {$file}" . PHP_EOL;
        return 0;
    }

    private function makeCrud(string $input): int
    {
        $name = $this->normalizeClassName($input, 'Model');
        $plural = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?: $name) . 's';
        $root = $this->applicationRoot();
        $files = [
            "/app/Models/{$name}.php" => str_replace('{{name}}', $name, <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class {{name}} extends Model
{
    protected $guarded = [];
}
PHP),
            "/app/Requests/{$name}Request.php" => str_replace('{{name}}', $name, <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Requests;

use Fx\Framework\Http\Request;

final class {{name}}Request extends Request
{
    public function validatedData(): array
    {
        return $this->validate(['name' => 'required|string|max:255']);
    }
}
PHP),
            "/app/Controllers/{$name}Controller.php" => $this->crudControllerTemplate($name),
            "/resources/views/{$plural}/index.tpl" => "<h1>{$name}</h1>\n",
            "/resources/views/{$plural}/form.tpl" => "-{csrf_field}-\n<label>Nome <input name=\"name\" required></label>\n",
            "/routes/generated/{$plural}.php" => str_replace(
                ['{{name}}', '{{uri}}'],
                [$name, $plural],
                <<<'PHP'
<?php

use App\Controllers\{{name}}Controller;

$router->resource('/{{uri}}', {{name}}Controller::class);
PHP
            ),
        ];
        foreach ($files as $relative => $contents) { $this->writeNew($root . $relative, $contents); }
        $migration = $root . '/database/migrations/' . date('Y_m_d_His') . '_create_' . $plural . '_table.php';
        $this->writeNew($migration, $this->migrationTemplate($plural));
        echo "CRUD {$name} criado com rotas REST em /{$plural}." . PHP_EOL;
        return 0;
    }

    private function runMigrations(bool $rollback): int
    {
        $bootstrap = $this->applicationRoot() . '/bootstrap/app.php';
        if (!is_file($bootstrap)) { throw new RuntimeException('Execute app:init ou crie bootstrap/app.php.'); }
        require $bootstrap;
        $migrator = new Migrator(Capsule::connection(), $this->applicationRoot() . '/database/migrations');
        $items = $rollback ? $migrator->rollback() : $migrator->migrate();
        echo $items === [] ? "Nenhuma migration processada.\n" : implode(PHP_EOL, $items) . PHP_EOL;
        return 0;
    }

    private function writeNew(string $file, string $contents): void
    {
        $file = str_replace('/', DIRECTORY_SEPARATOR, $file);
        if (is_file($file)) { return; }
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) { throw new RuntimeException("Nao foi possivel criar: {$directory}"); }
        if (file_put_contents($file, $contents, LOCK_EX) === false) { throw new RuntimeException("Nao foi possivel gravar: {$file}"); }
    }

    private function tableFromMigration(string $name): string
    {
        return preg_match('/^create_(.+)_table$/', $name, $match) === 1 ? $match[1] : $name;
    }

    private function migrationTemplate(string $table): string
    {
        return str_replace('{{table}}', $table, <<<'PHP'
<?php

use Fx\Framework\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class extends Migration {
    public function up(Builder $schema): void
    {
        $schema->create('{{table}}', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(Builder $schema): void
    {
        $schema->dropIfExists('{{table}}');
    }
};
PHP);
    }

    private function crudControllerTemplate(string $name): string
    {
        return str_replace('{{name}}', $name, <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\{{name}};
use App\Requests\{{name}}Request;

final class {{name}}Controller
{
    public function index(): array { return {{name}}::query()->paginate()->toArray(); }
    public function show(string $id): array { return {{name}}::query()->findOrFail($id)->toArray(); }
    public function store({{name}}Request $request): array { return {{name}}::query()->create($request->validatedData())->toArray(); }
    public function update({{name}}Request $request, string $id): array { $model = {{name}}::query()->findOrFail($id); $model->update($request->validatedData()); return $model->toArray(); }
    public function destroy(string $id): array { {{name}}::query()->findOrFail($id)->delete(); return ['deleted' => true]; }
}
PHP);
    }

    private function classTemplate(string $namespace, string $name, string $type): string
    {
        $extends = $type === 'Models'
            ? "\nuse Illuminate\\Database\\Eloquent\\Model;\n"
            : '';
        $declaration = $type === 'Models' ? "class {$name} extends Model" : "class {$name}";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};
{$extends}
{$declaration}
{
}
PHP;
    }

    private function clearDirectory(string $relativeDirectory): int
    {
        $directory = $this->applicationRoot() . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);

        if (!is_dir($directory)) {
            echo "Diretorio inexistente; nada para limpar: {$directory}" . PHP_EOL;
            return 0;
        }

        $removed = 0;
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isLink()) {
                if (unlink($item->getPathname())) {
                    $removed++;
                }
                continue;
            }

            if ($item->isFile() && $item->getFilename() !== '.gitkeep' && unlink($item->getPathname())) {
                $removed++;
            }

            if ($item->isDir() && !$this->directoryContainsGitkeep($item->getPathname())) {
                @rmdir($item->getPathname());
            }
        }

        echo "Cache limpo: {$removed} arquivo(s) removido(s)." . PHP_EOL;
        return 0;
    }

    private function directoryContainsGitkeep(string $directory): bool
    {
        return is_file($directory . DIRECTORY_SEPARATOR . '.gitkeep');
    }

    private function applicationRoot(): string
    {
        $root = getcwd();
        if ($root === false) {
            throw new RuntimeException('Nao foi possivel identificar a raiz da aplicacao.');
        }

        return $root;
    }

    private function unknown(string $command): int
    {
        if (in_array($command, self::LEGACY_COMMANDS, true)) {
            fwrite(STDERR, "O comando '{$command}' ainda pertence ao gerador legado do FX Corrente.\n");
            return 2;
        }

        fwrite(STDERR, "Comando desconhecido: {$command}. Use fxartisan help.\n");
        return 2;
    }
}
