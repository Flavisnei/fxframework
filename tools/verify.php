<?php
declare(strict_types=1);

// Mesmo ponto de entrada local e na CI. Sem instalacao ou configuracao implicita.
$root = dirname(__DIR__);
if ($argc !== 1) { fwrite(STDERR, "Uso: php tools/verify.php (sem argumentos)\n"); exit(1); }
$required = ['vendor/autoload.php', 'vendor/bin/phpunit'];
foreach (['minimal','http','modules','admin','contacts','smtp'] as $example) { $required[] = 'examples/' . $example . '/vendor/autoload.php'; }
foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) { fwrite(STDERR, 'Dependencia ausente: ' . $file . ". Consulte docs/index.html#compatibilidade.\n"); exit(1); }
}
if (!function_exists('proc_open')) { fwrite(STDERR, "Habilite proc_open no PHP CLI para executar as verificacoes.\n"); exit(1); }
$commands = [
    ['vendor/bin/phpunit', '--do-not-cache-result'],
    ['examples/minimal/verify.php'],
    ['examples/verify-package.php', 'http'],
    ['examples/modules/verify.php'],
    ['tests/integration/admin.php'],
    ['tests/integration/contacts.php'],
    ['tests/integration/smtp.php'],
];
echo 'PHP ' . PHP_VERSION . ' / ' . PHP_OS_FAMILY . PHP_EOL;
foreach ($commands as $command) {
    echo PHP_EOL . '> php ' . implode(' ', $command) . PHP_EOL;
    $process = proc_open([PHP_BINARY, ...$command], [0=>STDIN, 1=>STDOUT, 2=>STDERR], $pipes, $root, null, ['bypass_shell'=>true]);
    if (!is_resource($process)) { fwrite(STDERR, "Falha ao iniciar verificacao.\n"); exit(1); }
    $code = proc_close($process);
    if ($code !== 0) { fwrite(STDERR, "Verificacao interrompida: processo retornou $code.\n"); exit($code > 0 && $code < 256 ? $code : 1); }
}
echo PHP_EOL . "OK: sete etapas de verificacao concluidas. Nenhum envio SMTP externo.\n";
