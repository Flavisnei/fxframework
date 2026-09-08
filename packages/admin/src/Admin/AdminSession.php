<?php
declare(strict_types=1);
namespace Fx\Framework\Admin;

use Fx\Framework\Auth\SessionGuard;
use Fx\Framework\Http\Csrf;

final class AdminSession
{
    public function __construct(private readonly bool $secure = true, private readonly int $idleSeconds = 1800, private readonly int $absoluteSeconds = 28800, private readonly string $name = 'FXADMIN')
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9]{2,40}$/D', $name) || $idleSeconds < 1 || $absoluteSeconds < 1) { throw new \InvalidArgumentException('Configuracao de sessao Admin invalida.'); }
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (session_name() !== $this->name) { throw new \RuntimeException('Inicie Admin antes de outras sessoes; painel independente do WordPress.'); }
            return;
        }
        if (headers_sent()) { throw new \RuntimeException('Sessao Admin precisa iniciar antes da resposta.'); }
        session_name($this->name);
        if (!session_start(['use_strict_mode' => 1, 'use_only_cookies' => 1, 'use_trans_sid' => 0, 'cookie_httponly' => true, 'cookie_secure' => $this->secure, 'cookie_samesite' => 'Lax', 'cookie_lifetime' => 0])) { throw new \RuntimeException('Falha ao iniciar sessao Admin.'); }
    }
    public function user(AdminStore $store): ?AdminUser
    {
        $this->start();
        $meta = $_SESSION['_fx_admin_meta'] ?? null;
        if (!is_array($meta)) { return null; }
        $user = (new SessionGuard($store, '_fx_admin'))->user();
        if (!$user instanceof AdminUser || ($meta['version'] ?? null) !== (int) $user->data['auth_version'] || time() - ($meta['last'] ?? 0) >= $this->idleSeconds || time() - ($meta['created'] ?? 0) >= $this->absoluteSeconds) { $this->logout($store); return null; }
        $_SESSION['_fx_admin_meta']['last'] = time();
        return $user;
    }
    public function login(AdminStore $store, AdminUser $user): void
    {
        $this->start();
        (new SessionGuard($store, '_fx_admin'))->login($user);
        $_SESSION['_fx_admin_meta'] = ['version' => (int) $user->data['auth_version'], 'created' => time(), 'last' => time()];
        Csrf::regenerate();
    }
    public function logout(AdminStore $store): void
    {
        $this->start();
        (new SessionGuard($store, '_fx_admin'))->logout();
        unset($_SESSION['_fx_admin_meta']);
        Csrf::regenerate();
    }
}
