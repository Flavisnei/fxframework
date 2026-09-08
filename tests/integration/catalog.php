<?php

declare(strict_types=1);

// Composer real e autoload exclusivo do Console. Somente dados sintéticos.
require dirname(__DIR__, 2) . '/examples/console/vendor/autoload.php';
use Fx\Framework\Console\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

$composer = $argv[1] ?? null;
$temporary = sys_get_temp_dir() . '/fx-catalog-review-' . bin2hex(random_bytes(8));
foreach (['', '/package', '/project'] as $path) { mkdir($temporary . $path, 0700); }
$checks = 0;
function verifyCatalog(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
try {
    file_put_contents($temporary . '/package/composer.json', json_encode(['name'=>'fx-review/widget','version'=>'1.0.0','autoload'=>['psr-4'=>['FxReview\\'=>'./']]], JSON_THROW_ON_ERROR));
    file_put_contents($temporary . '/package/Widget.php', '<?php namespace FxReview; final class Widget { public static function ready(): bool { return true; } }');
    file_put_contents($temporary . '/project/composer.json', json_encode(['name'=>'fx-review/consumer','repositories'=>[['type'=>'path','url'=>'../package','options'=>['symlink'=>false]],['packagist.org'=>false]], 'scripts'=>['post-install-cmd'=>'@php -r "touch(\'script-ran\');"','post-update-cmd'=>'@php -r "touch(\'script-ran\');"']], JSON_THROW_ON_ERROR));
    $file = $temporary . '/catalog.json';
    file_put_contents($file, json_encode(['schema'=>1,'components'=>['widget'=>['package'=>'fx-review/widget','description'=>'Pacote sintético']], 'presets'=>['test'=>['widget']]], JSON_THROW_ON_ERROR));
    $hash = hash_file('sha256', $file);
    $app = new Artisan($temporary . '/project'); $app->setAutoExit(false);
    $args = ['command'=>'module:install','name'=>'widget','--catalog'=>$file,'--catalog-sha256'=>$hash];
    if ($composer !== null) { $args['--composer'] = $composer; }
    $output = new ConsoleOutput();
    $before = file_get_contents($temporary . '/project/composer.json');
    verifyCatalog($app->run(new ArrayInput($args + ['--dry-run'=>true]), $output) === 0, 'Dry-run falhou.');
    verifyCatalog(file_get_contents($temporary . '/project/composer.json') === $before && !is_dir($temporary . '/project/vendor'), 'Dry-run alterou consumidor.');
    verifyCatalog($app->run(new ArrayInput($args), $output) === 0, 'Instalacao real falhou.');
    verifyCatalog(!is_file($temporary . '/project/script-ran'), 'Script foi executado.');
    $manifest = file_get_contents($temporary . '/project/composer.json');
    $lock = file_get_contents($temporary . '/project/composer.lock');
    verifyCatalog(isset(json_decode($manifest, true)['require']['fx-review/widget']), 'Pacote nao registrado.');
    file_put_contents($temporary . '/project/probe.php', '<?php require __DIR__."/vendor/autoload.php"; if (!FxReview\\Widget::ready() || class_exists("Fx\\Framework\\Foundation\\CoreApplication")) { exit(1); }');
    $process = proc_open([PHP_BINARY, $temporary . '/project/probe.php'], [0=>['pipe','r'],1=>['pipe','w'],2=>['redirect',1]], $pipes, $temporary . '/project', null, ['bypass_shell'=>true]);
    if (!is_resource($process)) { throw new RuntimeException('Processo isolado indisponivel.'); }
    fclose($pipes[0]); $probe = stream_get_contents($pipes[1]); fclose($pipes[1]);
    verifyCatalog(proc_close($process) === 0, 'Autoload isolado falhou: ' . $probe);
    file_put_contents($file, ' ', FILE_APPEND);
    verifyCatalog($app->run(new ArrayInput($args), $output) !== 0, 'Catalogo adulterado aceito.');
    verifyCatalog(file_get_contents($temporary . '/project/composer.json') === $manifest && file_get_contents($temporary . '/project/composer.lock') === $lock, 'Rejeicao alterou manifesto ou lock.');
    echo "OK: {$checks} verificacoes de catalogo com Composer real e consumidor isolado.\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($temporary);
}
