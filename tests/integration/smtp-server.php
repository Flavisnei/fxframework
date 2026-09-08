<?php
declare(strict_types=1);
// Capturador descartavel: bind exclusivamente loopback, sem relay ou entrega externa.
$directory = $argv[1];
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$server) { throw new RuntimeException($error); }
file_put_contents($directory . '/address', stream_socket_get_name($server, false));
$client = stream_socket_accept($server, 20);
if (!$client) { exit(1); }
stream_set_timeout($client, 10);
fwrite($client, "220 localhost test SMTP\r\n");
$message = ''; $data = false;
while (($line = fgets($client)) !== false) {
    if ($data) {
        if ($line === ".\r\n") { file_put_contents($directory . '/message', $message); fwrite($client, "250 accepted locally\r\n"); $data = false; }
        else { $message .= str_starts_with($line, '..') ? substr($line, 1) : $line; }
    } elseif (str_starts_with($line, 'EHLO') || str_starts_with($line, 'HELO')) { fwrite($client, "250 localhost\r\n"); }
    elseif (str_starts_with($line, 'DATA')) { $data = true; fwrite($client, "354 send data\r\n"); }
    elseif (str_starts_with($line, 'QUIT')) { fwrite($client, "221 bye\r\n"); break; }
    else { fwrite($client, "250 OK\r\n"); }
}
fclose($client); fclose($server);
