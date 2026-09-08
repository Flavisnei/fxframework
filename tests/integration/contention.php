<?php
declare(strict_types=1);

// Processos concorrentes contra SQLite real em disco; sem contas ou dados do usuario.
require dirname(__DIR__, 2) . '/examples/contacts/vendor/autoload.php';
$workers = $argv[1] ?? '8';
if ($argc > 2 || !ctype_digit($workers) || (int)$workers < 2 || (int)$workers > 16) { fwrite(STDERR, "Uso: php tests/integration/contention.php [2 a 16 processos; padrao 8]\n"); exit(1); }
$workers = (int)$workers;
$directory = sys_get_temp_dir() . '/fx-contention-' . bin2hex(random_bytes(8));
if (!mkdir($directory,0700)) { throw new RuntimeException('Falha ao criar diretorio temporario.'); }
$processes=[]; $pipes=[]; $store=null;
try {
    $store = new Example\Contacts\ContactStore(new PDO('sqlite:' . $directory . '/contacts.sqlite'));
    $store->install(); $store->save(null,['name'=>'Contato inicial','email'=>'test@example.test']);
    for ($i=0;$i<$workers;$i++) {
        $process = proc_open([PHP_BINARY,__DIR__ . '/contention-worker.php',$directory,(string)$i], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $channels, dirname(__DIR__,2), null, ['bypass_shell'=>true]);
        if (!is_resource($process)) { throw new RuntimeException('Falha ao iniciar trabalhador.'); }
        fclose($channels[0]); $processes[$i]=$process; $pipes[$i]=$channels;
    }
    $deadline=microtime(true)+30;
    while (count(glob($directory . '/ready-*')) !== $workers) {
        if (microtime(true)>$deadline) { throw new RuntimeException('Trabalhadores nao chegaram a barreira.'); }
        usleep(20000); clearstatcache();
    }
    file_put_contents($directory . '/start','go');
    $results=[];
    foreach ($processes as $i=>$process) {
        $output=stream_get_contents($pipes[$i][1]); fclose($pipes[$i][1]);
        $error=stream_get_contents($pipes[$i][2]); fclose($pipes[$i][2]); unset($pipes[$i]);
        $code=proc_close($process); unset($processes[$i]);
        if ($code !== 0 || trim($error) !== '') { throw new RuntimeException('Trabalhador falhou: ' . $error); }
        $results[]=json_decode($output,true,32,JSON_THROW_ON_ERROR);
    }
    $successful=array_values(array_filter($results,fn($row)=>$row['status']===200));
    $conflicts=array_filter($results,fn($row)=>$row['status']===409);
    if (count($successful)!==1 || count($conflicts)!==$workers-1) { throw new RuntimeException('Esperada exatamente uma edicao e conflitos para as demais.'); }
    $contact=$store->get(1);
    if ($contact['version']!==2 || $contact['name']!=='Worker ' . $successful[0]['worker']) { throw new RuntimeException('Conteudo final nao corresponde ao unico vencedor.'); }
    try { $store->delete(1,1); throw new RuntimeException('Exclusao com versao antiga foi aceita.'); }
    catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) { if ($error->getStatusCode()!==409) throw $error; }
    if ($store->listing([])['total']!==1) { throw new RuntimeException('Contato foi perdido.'); }
    $times=array_column($results,'milliseconds'); sort($times,SORT_NUMERIC);
    echo json_encode(['schema'=>1,'php'=>PHP_VERSION,'os'=>PHP_OS_FAMILY,'workers'=>$workers,'successful_writes'=>1,'version_conflicts'=>count($conflicts),'final_version'=>2,'p50_ms'=>$times[(int)ceil(.5*$workers)-1],'p95_ms'=>$times[(int)ceil(.95*$workers)-1],'scope'=>'SQLite file, one row, simultaneous stale-version edits; no HTTP or throughput claim'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    foreach ($processes as $process) { proc_terminate($process); proc_close($process); }
    foreach ($pipes as $channels) { foreach ([1,2] as $index) if (is_resource($channels[$index])) fclose($channels[$index]); }
    $store=null; gc_collect_cycles();
    foreach (glob($directory . '/*') as $file) { unlink($file); }
    rmdir($directory);
}
