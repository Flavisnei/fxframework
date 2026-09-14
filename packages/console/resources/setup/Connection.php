<?php
declare(strict_types=1);
namespace App;

use PDO;
use RuntimeException;
use Symfony\Component\Dotenv\Dotenv;

/** Somente conexao PDO: nao cria banco, tabelas ou modelos. */
final class Connection
{
    public static function open(): PDO
    {
        $root = dirname(__DIR__);
        try {
            $values = (new Dotenv())->parse(file_get_contents($root . '/.env'), $root . '/.env');
            $read = static fn(string $key): string => getenv($key) !== false ? getenv($key) : ($values[$key] ?? '');
            if ($read('DB_DRIVER') === 'sqlite') {
                $path = $read('DB_DATABASE');
                if (!is_file($path)) { throw new RuntimeException(); }
                return new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            }
            if (!in_array($read('DB_DRIVER'), ['mysql', 'mariadb'], true)
                || !preg_match('/\A[a-zA-Z0-9.:-]+\z/', $read('DB_HOST'))
                || !preg_match('/\A[a-zA-Z0-9_]+\z/', $read('DB_DATABASE'))
                || !ctype_digit($read('DB_PORT')) || (int)$read('DB_PORT') < 1 || (int)$read('DB_PORT') > 65535) { throw new RuntimeException(); }
            return new PDO('mysql:host=' . $read('DB_HOST') . ';port=' . $read('DB_PORT') . ';dbname=' . $read('DB_DATABASE') . ';charset=utf8mb4', $read('DB_USERNAME'), $read('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        } catch (\Throwable) { throw new RuntimeException('Falha na conexao. Confira .env, extensao PDO e banco existente.'); }
    }
}
