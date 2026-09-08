<?php
declare(strict_types=1);

// Distribuicao local de fontes; nao publica, nao instala e nao cria tags.
$root = dirname(__DIR__);
function gitPackage(array $arguments, string $root): string
{
    $process = proc_open(['git', ...$arguments], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root, null, ['bypass_shell'=>true]);
    if (!is_resource($process)) { throw new RuntimeException('Nao foi possivel iniciar Git.'); }
    fclose($pipes[0]); $output = stream_get_contents($pipes[1]); fclose($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    if (proc_close($process) !== 0) { throw new RuntimeException('Git falhou: ' . trim($error)); }
    return $output;
}
function excludedPackagePath(string $path): bool
{
    $parts = explode('/', strtolower($path)); $name = end($parts);
    foreach ($parts as $part) { if (in_array($part, ['vendor','.git','storage','.phpunit.cache','node_modules'], true)) { return true; } }
    return ($name !== '.env.example' && str_starts_with($name, '.env'))
        || str_contains($name, '.local.') || preg_match('/\.(?:sqlite(?:3)?|db|log|pem|key)$/D', $name) === 1
        || $name === '.phpunit.result.cache';
}
$directory = null; $archive = null;
try {
    if ($argc !== 2 || trim($argv[1]) === '') { throw new InvalidArgumentException('Uso: php tools/package.php DIRETORIO_NOVO (fora do repositorio).'); }
    if (!class_exists(ZipArchive::class)) { throw new RuntimeException('Habilite a extensao ZIP no PHP CLI.'); }
    $parent = realpath(dirname($argv[1])); $name = basename($argv[1]);
    if ($parent === false || in_array($name, ['','.','..'], true) || file_exists($argv[1])) { throw new InvalidArgumentException('Use diretorio novo com pasta pai existente.'); }
    $directory = $parent . DIRECTORY_SEPARATOR . $name;
    $normalizedRoot = strtolower(str_replace('\\','/',realpath($root))) . '/';
    $normalizedOutput = strtolower(str_replace('\\','/',$directory)) . '/';
    if (str_starts_with($normalizedOutput, $normalizedRoot)) { throw new InvalidArgumentException('Grave a distribuicao fora do repositorio.'); }
    $commit = trim(gitPackage(['rev-parse','--verify','HEAD^{commit}'], $root));
    $tree = explode("\0", trim(gitPackage(['ls-tree','-rz','--full-tree',$commit], $root), "\0"));
    $excluded = [];
    foreach ($tree as $entry) {
        if (!preg_match('/^([0-9]+) blob [a-f0-9]+\t(.+)$/sD', $entry, $match)) { throw new RuntimeException('Submodulos ou entradas Git nao suportadas na distribuicao.'); }
        if (!in_array($match[1], ['100644','100755'], true)) { throw new RuntimeException('Links simbolicos nao sao permitidos na distribuicao.'); }
        if (excludedPackagePath($match[2])) { $excluded[] = $match[2]; }
    }
    if (!mkdir($directory, 0700)) { throw new RuntimeException('Nao foi possivel criar o diretorio de saida.'); }
    $archive = $directory . '/fx-framework-' . substr($commit,0,12) . '.zip';
    gitPackage(['archive','--format=zip','--output=' . $archive,$commit], $root);
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) { throw new RuntimeException('Nao foi possivel abrir o ZIP gerado.'); }
    // Git archive pode manter entradas de diretorios vazios apos remover arquivos.
    for ($index = 0; $index < $zip->numFiles; $index++) { $entry = $zip->getNameIndex($index); if (is_string($entry) && excludedPackagePath($entry) && !in_array($entry, $excluded, true)) { $excluded[] = $entry; } }
    foreach ($excluded as $file) { if ($zip->locateName($file) !== false && !$zip->deleteName($file)) { throw new RuntimeException('Falha ao filtrar o ZIP.'); } }
    $manifest = ['schema'=>1,'kind'=>'source-snapshot','commit'=>$commit,'source_date'=>trim(gitPackage(['show','-s','--format=%cI',$commit],$root)),'excluded_paths'=>$excluded,'dependencies_bundled'=>false];
    if (!$zip->addFromString('FX-DISTRIBUTION.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n") || !$zip->close()) { throw new RuntimeException('Falha ao finalizar ZIP.'); }
    $hash = hash_file('sha256',$archive);
    if (file_put_contents($archive . '.sha256', $hash . '  ' . basename($archive) . "\n") === false) { throw new RuntimeException('Falha ao escrever checksum.'); }
    echo json_encode(['commit'=>$commit,'archive'=>$archive,'sha256'=>$hash,'excluded_count'=>count($excluded)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    // Nao apagar diretorios recebidos; uma falha pode deixar somente artefatos desta tentativa.
    exit(1);
}
