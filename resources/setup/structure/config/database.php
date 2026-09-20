<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$values = is_file($root . '/.env') ? (new Symfony\Component\Dotenv\Dotenv())->parse(file_get_contents($root . '/.env')) : [];
$read = static fn (string $key, string $default = ''): string => getenv($key) !== false ? getenv($key) : ($values[$key] ?? $default);
// ORM instalado nao implica banco configurado. Nenhum arquivo/tabela e criado aqui.
if ($read('DB_DATABASE') === '') return null;
$driver = $read('DB_DRIVER', 'sqlite');
if (!in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) throw new RuntimeException('DB_DRIVER invalido.');
return ['driver' => $driver === 'mariadb' ? 'mysql' : $driver, 'database' => $read('DB_DATABASE'),
    'host' => $read('DB_HOST', '127.0.0.1'), 'port' => $read('DB_PORT', '3306'),
    'username' => $read('DB_USERNAME'), 'password' => $read('DB_PASSWORD'),
    'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => ''];
