<?php
declare(strict_types=1);
namespace Fx\Framework\Admin;

use Fx\Framework\Auth\Authenticatable;
use Fx\Framework\Auth\UserProvider;
use PDO;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AdminStore implements UserProvider
{
    public const PERMISSIONS = ['dashboard.view', 'users.view', 'users.manage', 'roles.manage', 'modules.view', 'modules.manage'];

    public function __construct(private readonly PDO $db)
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') { throw new \InvalidArgumentException('AdminStore exige SQLite nesta versao.'); }
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA busy_timeout = 5000');
    }

    private function query(string $sql, array $values = []): \PDOStatement
    {
        $query = $this->db->prepare($sql);
        $query->execute($values);
        return $query;
    }

    public function transaction(callable $action): mixed
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try { $result = $action(); $this->db->exec('COMMIT'); return $result; }
        catch (\Throwable $error) { $this->db->exec('ROLLBACK'); throw $error; }
    }

    /** Somente instalacao explicita por CLI; nunca chamada pelo bootstrap HTTP. */
    public function install(string $name, string $email, string $password): void
    {
        $this->transaction(function () use ($name, $email, $password): void {
            $this->db->exec('CREATE TABLE IF NOT EXISTS fx_admin_roles (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE, permissions TEXT NOT NULL)');
            $this->db->exec('CREATE TABLE IF NOT EXISTS fx_admin_users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password TEXT NOT NULL, role_id INTEGER NOT NULL REFERENCES fx_admin_roles(id), active INTEGER NOT NULL DEFAULT 1, auth_version INTEGER NOT NULL DEFAULT 1)');
            $this->db->exec('CREATE TABLE IF NOT EXISTS fx_admin_limits (key TEXT PRIMARY KEY, hits INTEGER NOT NULL, expires INTEGER NOT NULL)');
            $this->db->exec('CREATE TABLE IF NOT EXISTS fx_admin_resets (token TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES fx_admin_users(id), expires INTEGER NOT NULL)');
            if ((int) $this->query('SELECT COUNT(*) FROM fx_admin_users')->fetchColumn() > 0) { throw new \RuntimeException('Admin ja inicializado; nenhuma conta foi alterada.'); }
            $this->query('INSERT OR IGNORE INTO fx_admin_roles (id,name,permissions) VALUES (1,?,?)', ['Administrador', json_encode(self::PERMISSIONS)]);
            $this->writeUser(null, ['name' => $name, 'email' => $email, 'password' => $password, 'role_id' => 1, 'active' => true]);
        });
    }

    public function retrieveById(string|int $identifier): ?Authenticatable
    {
        $row = $this->query('SELECT * FROM fx_admin_users WHERE id=? AND active=1', [$identifier])->fetch();
        return $row ? new AdminUser($row) : null;
    }
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $email = $credentials['email'] ?? '';
        if (!is_string($email)) { return null; }
        $row = $this->query('SELECT * FROM fx_admin_users WHERE email=? AND active=1', [strtolower(trim($email))])->fetch();
        return $row ? new AdminUser($row) : null;
    }
    public function accountRole(int $id): ?int
    {
        $role = $this->query('SELECT role_id FROM fx_admin_users WHERE id=?', [$id])->fetchColumn();
        return $role === false ? null : (int) $role;
    }
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return $user instanceof AdminUser && is_string($credentials['password'] ?? null) && password_verify($credentials['password'], $user->data['password']);
    }
    public function permissions(AdminUser $user): array
    {
        $raw = $this->query('SELECT permissions FROM fx_admin_roles WHERE id=?', [$user->data['role_id']])->fetchColumn();
        return $raw === false ? [] : json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    }
    public function roles(): array
    {
        return array_map(function (array $role): array { $role['permissions'] = json_decode($role['permissions'], true, 32, JSON_THROW_ON_ERROR); return $role; }, $this->query('SELECT * FROM fx_admin_roles ORDER BY id')->fetchAll());
    }
    public function saveRole(?int $id, array $data): int
    {
        if ($id === 1) { throw new HttpException(409, 'O perfil Administrador e reservado.'); }
        $name = self::text($data, 'name', 2, 80);
        $permissions = $data['permissions'] ?? null;
        if (!is_array($permissions) || !array_is_list($permissions)) { throw new HttpException(422, 'permissions deve ser uma lista.'); }
        foreach ($permissions as $permission) { if (!is_string($permission) || !in_array($permission, self::PERMISSIONS, true)) { throw new HttpException(422, 'Permissao desconhecida.'); } }
        if ($id !== null && !$this->query('SELECT id FROM fx_admin_roles WHERE id=?', [$id])->fetchColumn()) { throw new HttpException(404, 'Perfil nao encontrado.'); }
        try {
            $values = [$name, json_encode(array_values(array_unique($permissions)))];
            if ($id === null) { $this->query('INSERT INTO fx_admin_roles(name,permissions) VALUES (?,?)', $values); return (int) $this->db->lastInsertId(); }
            $this->query('UPDATE fx_admin_roles SET name=?,permissions=? WHERE id=?', [...$values, $id]);
            return $id;
        } catch (\PDOException $error) { if ($error->getCode() === '23000') { throw new HttpException(409, 'Nome de perfil ja utilizado.'); } throw $error; }
    }
    public function users(int $page, string $search): array
    {
        $page = max(1, min(100000, $page));
        $search = '%' . mb_substr($search, 0, 100) . '%';
        $total = (int) $this->query('SELECT COUNT(*) FROM fx_admin_users WHERE name LIKE ? OR email LIKE ?', [$search, $search])->fetchColumn();
        $rows = $this->query('SELECT id,name,email,role_id,active FROM fx_admin_users WHERE name LIKE ? OR email LIKE ? ORDER BY id LIMIT 20 OFFSET ' . (($page - 1) * 20), [$search, $search])->fetchAll();
        return ['data' => $rows, 'page' => $page, 'total' => $total, 'per_page' => 20];
    }
    public function saveUser(?int $id, array $data): int
    {
        return $this->transaction(fn () => $this->writeUser($id, $data));
    }
    private function writeUser(?int $id, array $data): int
    {
        $name = self::text($data, 'name', 2, 120);
        $email = strtolower(self::text($data, 'email', 3, 254));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new HttpException(422, 'Email invalido.'); }
        $role = $data['role_id'] ?? null;
        if (!is_int($role) || !$this->query('SELECT id FROM fx_admin_roles WHERE id=?', [$role])->fetchColumn()) { throw new HttpException(422, 'Perfil invalido.'); }
        $active = $data['active'] ?? true;
        if (!is_bool($active)) { throw new HttpException(422, 'active deve ser booleano.'); }
        $current = $id === null ? null : $this->query('SELECT * FROM fx_admin_users WHERE id=?', [$id])->fetch();
        if ($id !== null && !$current) { throw new HttpException(404, 'Usuario nao encontrado.'); }
        if ($current && (int) $current['role_id'] === 1 && (int) $current['active'] === 1 && ($role !== 1 || !$active)) {
            if ((int) $this->query('SELECT COUNT(*) FROM fx_admin_users WHERE role_id=1 AND active=1')->fetchColumn() <= 1) { throw new HttpException(409, 'Mantenha pelo menos um administrador ativo.'); }
        }
        $password = $data['password'] ?? '';
        if (!is_string($password)) { throw new HttpException(422, 'Senha invalida.'); }
        $hash = $current['password'] ?? '';
        if ($id === null || $password !== '') { self::password($password); $hash = password_hash($password, PASSWORD_DEFAULT); }
        try {
            if ($id === null) { $this->query('INSERT INTO fx_admin_users(name,email,password,role_id,active) VALUES (?,?,?,?,?)', [$name, $email, $hash, $role, (int) $active]); return (int) $this->db->lastInsertId(); }
            $this->query('UPDATE fx_admin_users SET name=?,email=?,password=?,role_id=?,active=?,auth_version=auth_version+1 WHERE id=?', [$name, $email, $hash, $role, (int) $active, $id]);
            $this->query('DELETE FROM fx_admin_resets WHERE user_id=?', [$id]);
            return $id;
        } catch (\PDOException $error) { if ($error->getCode() === '23000') { throw new HttpException(409, 'Email ja utilizado.'); } throw $error; }
    }
    public static function text(array $data, string $key, int $min, int $max): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || mb_strlen(trim($value)) < $min || mb_strlen(trim($value)) > $max) { throw new HttpException(422, "Campo {$key} invalido ({$min} a {$max} caracteres)."); }
        return trim($value);
    }
    public static function password(string $password): void
    {
        if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) { throw new HttpException(422, 'Use senha entre 12 e 72 bytes.'); }
    }
    /** Contador persistente por janela; IP vem de REMOTE_ADDR, nao de cabecalho do cliente. */
    public function throttle(string $key, int $limit, int $seconds = 900): void
    {
        $now = time();
        $hits = $this->transaction(function () use ($key, $now, $seconds): int {
            $this->query('DELETE FROM fx_admin_limits WHERE expires<=?', [$now]);
            $key = hash('sha256', $key);
            $this->query('INSERT INTO fx_admin_limits(key,hits,expires) VALUES (?,1,?) ON CONFLICT(key) DO UPDATE SET hits=hits+1', [$key, $now + $seconds]);
            return (int) $this->query('SELECT hits FROM fx_admin_limits WHERE key=?', [$key])->fetchColumn();
        });
        if ($hits > $limit) { throw new HttpException(429, 'Muitas tentativas. Aguarde e tente novamente.', null, ['Retry-After' => (string) $seconds]); }
    }
    public function resetToken(AdminUser $user): string
    {
        $token = bin2hex(random_bytes(32));
        $this->transaction(function () use ($user, $token): void {
            $this->query('DELETE FROM fx_admin_resets WHERE user_id=? OR expires<=?', [$user->getAuthIdentifier(), time()]);
            $this->query('INSERT INTO fx_admin_resets(token,user_id,expires) VALUES (?,?,?)', [hash('sha256', $token), $user->getAuthIdentifier(), time() + 1800]);
        });
        return $token;
    }
    public function resetPassword(string $token, string $password): void
    {
        self::password($password);
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) { throw new HttpException(422, 'Link invalido ou expirado.'); }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->transaction(function () use ($token, $hash): void {
            $record = $this->query('SELECT r.user_id FROM fx_admin_resets r JOIN fx_admin_users u ON u.id=r.user_id WHERE token=? AND expires>? AND u.active=1', [hash('sha256', $token), time()])->fetch();
            if (!$record) { throw new HttpException(422, 'Link invalido ou expirado.'); }
            $this->query('UPDATE fx_admin_users SET password=?,auth_version=auth_version+1 WHERE id=?', [$hash, $record['user_id']]);
            $this->query('DELETE FROM fx_admin_resets WHERE user_id=?', [$record['user_id']]);
        });
    }
}
