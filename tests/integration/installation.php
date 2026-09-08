<?php

declare(strict_types=1);

// Teste opt-in com Composer real. Somente cria projetos temporarios novos.
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Fx\Framework\Console\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

$composer = $argv[1] ?? null;
$root = str_replace('\\', '/', dirname(__DIR__, 2));
$temporary = sys_get_temp_dir() . '/fx-install-review-' . bin2hex(random_bytes(8));
mkdir($temporary);
echo "Projetos descartaveis: {$temporary}\n";
$checks = 0;
function checkInstall(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
function installCommand(string $directory, string $command, string $name, string $constraint = '@dev', bool $dryRun = false): int
{
    global $composer;
    $artisan = new Artisan($directory);
    $artisan->setAutoExit(false);
    $args = ['command' => $command, 'name' => $name, '--constraint' => $constraint];
    if ($composer !== null) { $args['--composer'] = $composer; }
    if ($dryRun) { $args['--dry-run'] = true; }
    return $artisan->run(new ArrayInput($args), new ConsoleOutput());
}
foreach (['minimal', 'api', 'wordpress'] as $preset) {
    $directory = $temporary . '/' . $preset;
    mkdir($directory);
    $manifest = json_encode([
        'name' => 'fx-review/' . $preset,
        'repositories' => [['type' => 'path', 'url' => $root . '/packages/*', 'options' => ['symlink' => false]]],
        'require' => ['php' => '^8.1'],
        'minimum-stability' => 'dev', 'prefer-stable' => true,
        'scripts' => ['post-update-cmd' => '@php -r "file_put_contents(\'script-ran\', \'yes\');"'],
        'config' => ['allow-plugins' => false],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($directory . '/composer.json', $manifest);
    checkInstall(installCommand($directory, 'preset:install', $preset, '@dev', true) === 0, 'Simulacao falhou.');
    checkInstall(file_get_contents($directory . '/composer.json') === $manifest && !is_file($directory . '/composer.lock') && !is_dir($directory . '/vendor'), 'Simulacao alterou projeto.');
    checkInstall(installCommand($directory, 'preset:install', $preset) === 0, 'Instalacao falhou.');
    checkInstall(!is_file($directory . '/script-ran'), 'Script executado indevidamente.');
    $locked = file_get_contents($directory . '/composer.lock');
    $installedManifest = file_get_contents($directory . '/composer.json');
    checkInstall(installCommand($directory, 'module:install', 'core', '^999.0') !== 0, 'Conflito nao reportado.');
    checkInstall(file_get_contents($directory . '/composer.lock') === $locked && file_get_contents($directory . '/composer.json') === $installedManifest, 'Falha de resolucao nao restaurou manifesto/lock.');
    if ($preset === 'minimal') {
        checkInstall(installCommand($directory, 'module:install', 'windows', '@dev', true) === 0, 'Simulacao com lock falhou.');
        checkInstall(file_get_contents($directory . '/composer.lock') === $locked && file_get_contents($directory . '/composer.json') === $installedManifest, 'Simulacao modificou lock existente.');
        checkInstall(installCommand($directory, 'module:install', 'windows') === 0, 'Componente individual falhou.');
        checkInstall(installCommand($directory, 'preset:install', 'minimal') === 0, 'Preset repetido falhou.');
        $requirements = json_decode(file_get_contents($directory . '/composer.json'), true)['require'];
        checkInstall(isset($requirements['fxfavalessa/fx-windows']) && $requirements['php'] === '^8.1', 'Preset removeu requisito existente.');
    }
    // Processo novo: somente o autoload do consumidor, sem classes da distribuicao completa.
    $probe = <<<'PHP'
<?php
require __DIR__ . '/vendor/autoload.php';
$preset = $argv[1];
foreach (['database', 'view', 'auth'] as $component) {
    if (Composer\InstalledVersions::isInstalled('fxfavalessa/fx-' . $component)) { throw new RuntimeException('Dependencia inesperada.'); }
}
$app = new Fx\Framework\Foundation\CoreApplication(__DIR__);
$app->config()->set('ok', true);
if ($app->config()->get('ok') !== true) { throw new RuntimeException('Core nao funciona.'); }
if ($preset === 'api') {
    $app = new Fx\Framework\Foundation\Application(__DIR__);
    $app->make(Fx\Framework\Routing\Router::class)->get('/ping', fn () => ['ok' => true]);
    $response = $app->make(Fx\Framework\Http\Kernel::class)->handle(Fx\Framework\Http\Request::create('/ping'));
    if ($response->getContent() !== '{"ok":true}') { throw new RuntimeException('API indisponivel.'); }
    $cli = new Fx\Framework\Console\Artisan(__DIR__);
    if (!$cli->has('module:install') || !$cli->has('route:list') || $cli->has('migrate')) { throw new RuntimeException('Comandos incorretos.'); }
} elseif (class_exists(Fx\Framework\Foundation\Application::class)) { throw new RuntimeException('HTTP inesperado.'); }
if ($preset === 'wordpress' && !class_exists(Fx\Framework\WordPress\Plugin::class)) { throw new RuntimeException('Adaptador ausente.'); }
if ($preset === 'minimal' && !is_file(Fx\Framework\Windows\Assets::directory() . '/fxwindows.js')) { throw new RuntimeException('Assets ausentes.'); }
echo "Autoload isolado OK: {$preset}\n";
PHP;
    file_put_contents($directory . '/probe.php', $probe);
    $process = proc_open([PHP_BINARY, $directory . '/probe.php', $preset], [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $directory, null, ['bypass_shell' => true]);
    if (!is_resource($process)) { throw new RuntimeException('Falha ao iniciar probe.'); }
    fclose($pipes[0]);
    checkInstall(proc_close($process) === 0, 'Autoload isolado falhou.');
}
echo "OK: {$checks} verificacoes com Composer real. Temporarios preservados para inspecao.\n";
