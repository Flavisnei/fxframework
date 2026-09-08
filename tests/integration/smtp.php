<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/examples/smtp/vendor/autoload.php';

use Fx\Framework\Admin\Mail\ResetMailQueue;
use Fx\Framework\Admin\Mail\SmtpSender;

$directory = sys_get_temp_dir() . '/fx-smtp-test-' . bin2hex(random_bytes(8));
mkdir($directory);
$process = proc_open([PHP_BINARY, __DIR__ . '/smtp-server.php', $directory], [0 => ['pipe', 'r'], 1 => ['file', $directory . '/server.log', 'a'], 2 => ['file', $directory . '/server.log', 'a']], $pipes, $directory, null, ['bypass_shell' => true]);
if (!is_resource($process)) { throw new RuntimeException('Capturador nao iniciou.'); }
fclose($pipes[0]);
$checks = 0;
function smtpCheck(bool $condition, string $message): void { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }
try {
    for ($i = 0; $i < 50 && !is_file($directory . '/address'); $i++) { usleep(100000); }
    $address = trim(file_get_contents($directory . '/address'));
    $sender = new SmtpSender('smtp://' . $address, 'sender@example.test', true);
    $db = new PDO('sqlite:' . $directory . '/queue.sqlite');
    $queue = new ResetMailQueue($db, bin2hex(random_bytes(32)), 'https://example.test/admin');
    $queue->install();
    $token = bin2hex(random_bytes(32));
    $queue->enqueue('recipient@example.test', $token);
    smtpCheck($queue->work($sender)['sent'] === 1, 'Envio SMTP local falhou.');
    $raw = file_get_contents($directory . '/message');
    [$headers, $body] = explode("\r\n\r\n", $raw, 2);
    $message = $headers . "\r\n\r\n" . (stripos($headers, 'Content-Transfer-Encoding: base64') !== false ? base64_decode($body, true) : quoted_printable_decode($body));
    smtpCheck(str_contains($message, 'recipient@example.test'), 'Destinatario ausente.');
    smtpCheck(str_contains($message, 'sender@example.test'), 'Remetente ausente.');
    smtpCheck(str_contains($message, 'https://example.test/admin#reset=' . $token), 'Link invalido.');
    smtpCheck($queue->status()['pending'] === 0, 'Mensagem nao removida.');
    foreach (['smtp://remote.example.test:25', 'smtps://remote.example.test?verify_peer=0', 'smtp://user:secret@127.0.0.1:25'] as $dsn) {
        try { new SmtpSender($dsn, 'sender@example.test', true); throw new LogicException('Configuracao insegura aceita.'); }
        catch (RuntimeException $error) { smtpCheck(!str_contains($error->getMessage(), 'secret'), 'Segredo vazou.'); }
    }
    echo "OK: {$checks} verificacoes SMTP local; nenhuma mensagem externa enviada.\n";
} finally { unset($sender); proc_terminate($process); proc_close($process); }
