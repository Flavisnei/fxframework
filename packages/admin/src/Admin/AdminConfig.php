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
        if (!is_array($config) || !is_string($config['database'] ?? null) || !preg_match('~^(?:[A-Za-z]:[/\\\\]|/)~', $config['database'])) { throw new \RuntimeException('Configure database com caminho absoluto de arquivo SQLite fora de public.'); }
        return $config;
    }
    public static function store(array $config, bool $create = false): AdminStore
    {
        $file = $config['database'];
        if (!$create && !is_file($file)) { throw new \RuntimeException('Banco Admin ausente. Execute admin:init pelo CLI.'); }
        if ($create && !is_dir(dirname($file)) && !mkdir(dirname($file), 0770, true) && !is_dir(dirname($file))) { throw new \RuntimeException('Nao foi possivel criar storage do Admin.'); }
        return new AdminStore(new \PDO('sqlite:' . $file));
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
