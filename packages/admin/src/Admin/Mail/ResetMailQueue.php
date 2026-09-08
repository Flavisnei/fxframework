<?php
declare(strict_types=1);
namespace Fx\Framework\Admin\Mail;

use PDO;
use RuntimeException;

/** Fila local SQLite. Payload autenticado/cifrado; segredo mantido fora do banco. */
final class ResetMailQueue
{
    private readonly string $key;
    public function __construct(private readonly PDO $db, string $hexKey, private readonly string $adminUrl)
    {
        if (!extension_loaded('openssl') || !in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) { throw new RuntimeException('A fila exige OpenSSL com AES-256-GCM.'); }
        if (!preg_match('/^[a-fA-F0-9]{64}$/D', $hexKey)) { throw new RuntimeException('FX_MAIL_KEY deve conter 32 bytes aleatorios em hexadecimal (64 caracteres).'); }
        $url = parse_url($adminUrl);
        if (!$url || !filter_var($adminUrl, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x20]/', $adminUrl) || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || ($url['path'] ?? '') !== '/admin') { throw new RuntimeException('FX_ADMIN_URL deve ser uma URL HTTPS fixa terminada em /admin, sem credenciais, query ou fragmento.'); }
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') { throw new RuntimeException('A fila exige SQLite.'); }
        $this->key = hex2bin($hexKey);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA busy_timeout = 5000');
    }
    private function query(string $sql, array $values = []): \PDOStatement
    {
        $statement = $this->db->prepare($sql); $statement->execute($values); return $statement;
    }
    private function transaction(callable $callback): mixed
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try { $result = $callback(); $this->db->exec('COMMIT'); return $result; }
        catch (\Throwable $error) { $this->db->exec('ROLLBACK'); throw $error; }
    }
    public function install(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS fx_admin_mail (id TEXT PRIMARY KEY, recipient_hash TEXT NOT NULL UNIQUE, payload TEXT NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, available INTEGER NOT NULL, expires INTEGER NOT NULL, lease TEXT, reserved_until INTEGER NOT NULL DEFAULT 0)');
    }
    public function assertReady(): void
    {
        if (!$this->query("SELECT name FROM sqlite_master WHERE type='table' AND name='fx_admin_mail'")->fetchColumn()) { throw new RuntimeException('Inicialize a fila pelo comando admin:mail --init antes de habilitar recuperacao SMTP.'); }
    }
    public function enqueue(string $email, string $token): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-f0-9]{64}$/D', $token)) { throw new RuntimeException('Destinatario ou token invalido.'); }
        $plain = json_encode(['email' => $email, 'url' => $this->adminUrl . '#reset=' . $token], JSON_THROW_ON_ERROR);
        $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag, 'fx-admin-reset-v1', 16);
        if ($cipher === false) { throw new RuntimeException('Falha ao cifrar a mensagem.'); }
        $payload = base64_encode($iv . $tag . $cipher);
        $this->transaction(function () use ($email, $payload): void {
            $this->query('DELETE FROM fx_admin_mail WHERE recipient_hash=? OR expires<=?', [hash('sha256', strtolower($email)), time()]);
            $this->query('INSERT INTO fx_admin_mail(id,recipient_hash,payload,available,expires) VALUES (?,?,?,?,?)', [bin2hex(random_bytes(16)), hash('sha256', strtolower($email)), $payload, time(), time() + 1800]);
        });
    }
    public function status(): array
    {
        return ['pending' => (int) $this->query('SELECT COUNT(*) FROM fx_admin_mail WHERE attempts<5 AND expires>?', [time()])->fetchColumn(), 'failed' => (int) $this->query('SELECT COUNT(*) FROM fx_admin_mail WHERE attempts>=5 AND expires>?', [time()])->fetchColumn(), 'expired' => (int) $this->query('SELECT COUNT(*) FROM fx_admin_mail WHERE expires<=?', [time()])->fetchColumn()];
    }
    /** sender(email, url). Falhas nao incluem payload/credenciais na saida. Entrega pelo menos uma vez. */
    public function work(callable $sender, int $limit = 20): array
    {
        if ($limit < 1 || $limit > 100) { throw new RuntimeException('Limite deve estar entre 1 e 100.'); }
        $result = ['sent' => 0, 'failed' => 0, 'expired' => $this->query('DELETE FROM fx_admin_mail WHERE expires<=?', [time()])->rowCount()];
        for ($i = 0; $i < $limit; $i++) {
            $job = $this->transaction(function (): array|false {
                $job = $this->query('SELECT * FROM fx_admin_mail WHERE attempts<5 AND available<=? AND reserved_until<=? AND expires>? ORDER BY available,id LIMIT 1', [time(), time(), time()])->fetch();
                if (!$job) { return false; }
                $job['lease'] = bin2hex(random_bytes(16));
                $this->query('UPDATE fx_admin_mail SET lease=?,reserved_until=?,attempts=attempts+1 WHERE id=?', [$job['lease'], time() + 300, $job['id']]);
                return $job;
            });
            if (!$job) { break; }
            try {
                $bytes = base64_decode($job['payload'], true);
                if ($bytes === false || strlen($bytes) < 29) { throw new RuntimeException('Payload invalido.'); }
                $plain = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), 'fx-admin-reset-v1');
                if ($plain === false) { throw new RuntimeException('Falha na autenticacao do payload.'); }
                $payload = json_decode($plain, true, 8, JSON_THROW_ON_ERROR);
                $sender($payload['email'], $payload['url']);
                $this->query('DELETE FROM fx_admin_mail WHERE id=? AND lease=?', [$job['id'], $job['lease']]);
                $result['sent']++;
            } catch (\Throwable) {
                $this->query('UPDATE fx_admin_mail SET lease=NULL,reserved_until=0,available=? WHERE id=? AND lease=?', [time() + min(900, 30 * (2 ** (int) $job['attempts'])), $job['id'], $job['lease']]);
                $result['failed']++;
            }
        }
        return $result;
    }
}
