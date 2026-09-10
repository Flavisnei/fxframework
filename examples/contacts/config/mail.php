<?php
declare(strict_types=1);
// Variaveis reais do ambiente; o framework nao carrega .env automaticamente.
$file = __DIR__ . '/mail.local.json';
$local = is_file($file) ? json_decode(file_get_contents($file), true, 16, JSON_THROW_ON_ERROR) : [];
if (!is_array($local)) { throw new RuntimeException('Configuracao local de email invalida.'); }
return [
    'dsn' => getenv('FX_MAIL_DSN') ?: ($local['dsn'] ?? ''),
    'from' => getenv('FX_MAIL_FROM') ?: ($local['from'] ?? ''),
    'admin_url' => getenv('FX_ADMIN_URL') ?: ($local['admin_url'] ?? ''),
    'key' => getenv('FX_MAIL_KEY') ?: ($local['key'] ?? ''),
];
