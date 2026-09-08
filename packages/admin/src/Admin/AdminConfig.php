<?php
declare(strict_types=1);
namespace Fx\Framework\Admin;

use Fx\Framework\Modules\ModuleManager;

final class AdminConfig
{
    public static function read(string $root): array
    {
        $file = $root . '/config/admin.php';
        if (!is_file($file)) { throw new \RuntimeException('Crie config/admin.php conforme a ajuda do Admin.'); }
        $config = require $file;
        if (!is_array($config)) { throw new \RuntimeException('Configuracao Admin invalida.'); }
        self::validateDatabase($config['database'] ?? null);
        return $config;
    }
    private static function validateDatabase(mixed $database): void
    {
        if (is_string($database) && preg_match('~^(?:[A-Za-z]:[/\\\\]|/)~', $database)) { return; }
        if (is_array($database) && ($database['driver'] ?? null) === 'mysql'
            && is_string($database['host'] ?? null) && preg_match('/\A[a-zA-Z0-9.:-]+\z/', $database['host'])
            && is_string($database['name'] ?? null) && preg_match('/\A[a-zA-Z0-9_]+\z/', $database['name'])
            && is_string($database['username'] ?? null) && is_string($database['password'] ?? null)
            && is_int($database['port'] ?? 3306) && ($database['port'] ?? 3306) >= 1 && ($database['port'] ?? 3306) <= 65535) { return; }
        throw new \RuntimeException('Use database como caminho SQLite absoluto ou configuracao mysql com host, name, username, password e port inteiro.');
    }
    public static function connection(array $config, bool $create = false): \PDO
    {
        $database = $config['database'] ?? null; self::validateDatabase($database);
        if (is_array($database)) {
            try { return new \PDO('mysql:host='.$database['host'].';port='.($database['port'] ?? 3306).';dbname='.$database['name'].';charset=utf8mb4', $database['username'], $database['password'], [\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES=>false, \PDO::ATTR_STRINGIFY_FETCHES=>false]); }
            catch (\PDOException) { throw new \RuntimeException('Falha ao conectar ao banco Admin MySQL/MariaDB. Confira extensao PDO, banco existente e credenciais.'); }
        }
        if (!$create && !is_file($database)) { throw new \RuntimeException('Banco Admin ausente. Execute admin:init pelo CLI.'); }
        if ($create && !is_dir(dirname($database)) && !mkdir(dirname($database), 0770, true) && !is_dir(dirname($database))) { throw new \RuntimeException('Nao foi possivel criar storage do Admin.'); }
        return new \PDO('sqlite:' . $database);
    }
    public static function store(array $config, bool $create = false): AdminStore
    {
        return new AdminStore(self::connection($config, $create), $config['permissions'] ?? []);
    }
    public static function panel(string $root): Panel
    {
        $config = self::read($root);
        $delivery = $config['reset_delivery'] ?? null;
        if (isset($config['mail'])) {
            if ($delivery !== null) { throw new \RuntimeException('Configure mail ou reset_delivery, nao ambos.'); }
            $queue = \Fx\Framework\Admin\Mail\MailConfig::queue($config); $queue->assertReady();
            $delivery = $queue->enqueue(...);
        }
        return new Panel(self::store($config), new AdminSession($config['secure_cookie'] ?? true, 1800, 28800, $config['session_name'] ?? ('FXA' . substr(hash('sha256', realpath($root) ?: $root), 0, 16))), new ModuleManager($root), $delivery, $config['logger'] ?? null);
    }
}
