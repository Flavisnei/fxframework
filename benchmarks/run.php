<?php
declare(strict_types=1);

// Somente ferramentas nativas; resultado JSON em stdout, progresso em stderr.
if ($argc > 2 || (isset($argv[1]) && (!ctype_digit($argv[1]) || (int) $argv[1] < 5 || (int) $argv[1] > 200))) { fwrite(STDERR, "Uso: php benchmarks/run.php [amostras de 5 a 200; padrao 20]\n"); exit(1); }
$samples = (int) ($argv[1] ?? 20); $warmups = 2; $root = dirname(__DIR__);
$scenarios = ['core-minimal'=>'examples/minimal', 'core-complete'=>'.', 'http-json'=>'examples/http', 'contacts-page'=>'examples/contacts'];
$report = ['schema'=>1,'utc'=>gmdate('c'),'environment'=>['php'=>PHP_VERSION,'os'=>PHP_OS_FAMILY,'architecture_bits'=>PHP_INT_SIZE*8,'sapi'=>PHP_SAPI,'opcache_enable_cli'=>ini_get('opcache.enable_cli'),'xdebug_loaded'=>extension_loaded('xdebug'),'sqlite'=>class_exists('SQLite3') ? SQLite3::version()['versionString'] : null], 'samples'=>$samples,'warmups_per_scenario'=>$warmups,'scenarios'=>[]];
$report['environment']['sqlite'] = (new PDO('sqlite::memory:'))->getAttribute(PDO::ATTR_SERVER_VERSION);
$report['environment']['extensions'] = get_loaded_extensions(); sort($report['environment']['extensions']);
$report['benchmark_sha256'] = ['run.php'=>hash_file('sha256', __FILE__), 'worker.php'=>hash_file('sha256', __DIR__ . '/worker.php')];

function stats(array $values): array
{
    sort($values, SORT_NUMERIC); $count = count($values);
    return ['min'=>$values[0], 'p50'=>$values[(int) ceil(.5*$count)-1], 'p95'=>$values[(int) ceil(.95*$count)-1], 'max'=>$values[$count-1]];
}

// Inventario antes de medir; arquivos locais aquecidos pelo inventario.
foreach ($scenarios as $name=>$directory) {
    $vendor = $root . '/' . $directory . '/vendor';
    if (!is_file($vendor . '/autoload.php') || !is_file($vendor . '/composer/installed.json')) { fwrite(STDERR, "Execute composer install em $directory antes do benchmark.\n"); exit(1); }
    $installed = json_decode(file_get_contents($vendor . '/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
    $packages = []; foreach ($installed['packages'] as $package) { $packages[$package['name']] = $package['version']; } ksort($packages);
    $bytes=0; $files=0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vendor, FilesystemIterator::SKIP_DOTS)) as $file) { if ($file->isFile()) { $bytes += $file->getSize(); $files++; } }
    $report['scenarios'][$name] = ['packages'=>$packages,'installed_with_dev'=>$installed['dev'] ?? null,'vendor_files'=>$files,'vendor_bytes'=>$bytes,'lock_sha256'=>is_file($root . '/' . $directory . '/composer.lock') ? hash_file('sha256',$root . '/' . $directory . '/composer.lock') : null,'metrics'=>[]];
}
$rows = array_fill_keys(array_keys($scenarios), []);
// Alterna os cenarios para reduzir o vies de executa-los em blocos separados.
for ($round=-$warmups;$round<$samples;$round++) {
    foreach ($scenarios as $name=>$directory) {
        $process = proc_open([PHP_BINARY, '-d', 'opcache.enable_cli=' . (int) ini_get('opcache.enable_cli'), __DIR__ . '/worker.php', $name], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root, null, ['bypass_shell'=>true]);
        if (!is_resource($process)) { throw new RuntimeException('Falha ao iniciar amostra.'); }
        fclose($pipes[0]); $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]); $code = proc_close($process);
        if ($code !== 0 || trim($stderr) !== '') { throw new RuntimeException("Amostra $name falhou: " . $stderr); }
        $row = json_decode($stdout, true, 32, JSON_THROW_ON_ERROR);
        if ($round >= 0) { $rows[$name][] = $row; }
    }
    fwrite(STDERR, $round < 0 ? "Aquecimento concluido.\n" : 'Rodada ' . ($round+1) . "/$samples concluida.\n");
}
foreach ($rows as $name=>$data) { foreach (array_keys($data[0]) as $metric) { $report['scenarios'][$name]['metrics'][$metric] = stats(array_column($data,$metric)); } }
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
