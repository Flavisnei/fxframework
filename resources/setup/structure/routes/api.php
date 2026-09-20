<?php
declare(strict_types=1);
// Rotas explicitas: nao recebem prefixo automatico. POST/PUT/DELETE usam CSRF.
$router->get('/api/status', fn (): array => ['status' => 'ok', 'framework' => 'FX']);
