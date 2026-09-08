<?php
declare(strict_types=1);
return [
    'database' => dirname(__DIR__) . '/storage/admin.sqlite',
    // Exemplo local HTTP. Use true em HTTPS; nao confie em headers arbitrarios de proxy.
    'secure_cookie' => false,
    // Integre uma fila de email. Callback recebe email e token; nunca devolva token pela API.
    'reset_delivery' => null,
    'logger' => static function (string $event, array $context): void {
        file_put_contents(dirname(__DIR__) . '/storage/admin.log', json_encode(['time' => gmdate('c'), 'event' => $event, 'context' => $context], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    },
];
