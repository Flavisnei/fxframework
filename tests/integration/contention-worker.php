<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/examples/contacts/vendor/autoload.php';
$directory = $argv[1] ?? ''; $worker = $argv[2] ?? '';
if (!is_dir($directory) || !ctype_digit($worker)) { exit(1); }
$store = new Example\Contacts\ContactStore(new PDO('sqlite:' . $directory . '/contacts.sqlite'));
$contact = $store->get(1);
file_put_contents($directory . '/ready-' . $worker, 'ready');
$deadline = microtime(true) + 30;
while (!is_file($directory . '/start')) {
    if (microtime(true) > $deadline) { fwrite(STDERR, "Barreira expirou.\n"); exit(1); }
    usleep(10000); clearstatcache(true, $directory . '/start');
}
$start = hrtime(true);
try {
    $store->save(1, [...$contact, 'name'=>'Worker ' . $worker]); $status = 200;
} catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
    $status = $error->getStatusCode();
}
echo json_encode(['worker'=>(int)$worker,'status'=>$status,'milliseconds'=>(hrtime(true)-$start)/1e6], JSON_THROW_ON_ERROR) . PHP_EOL;
