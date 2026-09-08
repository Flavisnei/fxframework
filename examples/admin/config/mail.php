<?php
declare(strict_types=1);
// Variaveis reais do ambiente; o framework nao carrega .env automaticamente.
return [
    'dsn' => getenv('FX_MAIL_DSN') ?: '',
    'from' => getenv('FX_MAIL_FROM') ?: '',
    'admin_url' => getenv('FX_ADMIN_URL') ?: '',
    'key' => getenv('FX_MAIL_KEY') ?: '',
];
