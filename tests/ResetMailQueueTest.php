<?php
declare(strict_types=1);
namespace Fx\Framework\Tests;

use Fx\Framework\Admin\Mail\ResetMailQueue;
use PHPUnit\Framework\TestCase;

final class ResetMailQueueTest extends TestCase
{
    private \PDO $db;
    private ResetMailQueue $queue;
    private string $key;
    private string $token;
    protected function setUp(): void
    {
        $this->db = new \PDO('sqlite::memory:'); $this->key = bin2hex(random_bytes(32)); $this->token = bin2hex(random_bytes(32));
        $this->queue = new ResetMailQueue($this->db, $this->key, 'https://example.test/admin');
        $this->queue->install();
    }
    public function testQueueDoesNotExposeRecipientOrTokenAndWorkerBuildsConfiguredLink(): void
    {
        $this->queue->enqueue('user@example.test', $this->token);
        $raw = json_encode($this->db->query('SELECT * FROM fx_admin_mail')->fetchAll());
        self::assertStringNotContainsString($this->token, $raw);
        self::assertStringNotContainsString('user@example.test', $raw);
        $delivered = [];
        self::assertSame(['sent' => 1, 'failed' => 0, 'expired' => 0], $this->queue->work(function ($email, $url) use (&$delivered): void { $delivered = [$email, $url]; }));
        self::assertSame(['user@example.test', 'https://example.test/admin#reset=' . $this->token], $delivered);
        self::assertSame(0, $this->queue->status()['pending']);
    }
    public function testTamperedPayloadAndWrongKeyNeverReachSender(): void
    {
        $this->queue->enqueue('user@example.test', $this->token);
        $wrong = new ResetMailQueue($this->db, bin2hex(random_bytes(32)), 'https://example.test/admin');
        $sender = function (): void { self::fail('Payload sem autenticidade enviado.'); };
        self::assertSame(1, $wrong->work($sender)['failed']);
        $this->db->exec("UPDATE fx_admin_mail SET payload='invalid',available=0");
        self::assertSame(1, $this->queue->work($sender)['failed']);
    }
    public function testSupersededAndExpiredMessagesAreNotSent(): void
    {
        $this->queue->enqueue('user@example.test', $this->token);
        $new = bin2hex(random_bytes(32)); $this->queue->enqueue('user@example.test', $new);
        self::assertSame(1, $this->queue->status()['pending']);
        $sent = '';
        $this->queue->work(function ($email, $url) use (&$sent): void { $sent = $url; });
        self::assertStringEndsWith($new, $sent);
        $this->queue->enqueue('user@example.test', $this->token); $this->db->exec('UPDATE fx_admin_mail SET expires=0');
        self::assertSame(['sent' => 0, 'failed' => 0, 'expired' => 1], $this->queue->work(fn () => self::fail('Expirado enviado.')));
    }
    public function testAnotherWorkerCannotClaimAnUnexpiredLease(): void
    {
        $this->queue->enqueue('user@example.test', $this->token);
        $other = new ResetMailQueue($this->db, $this->key, 'https://example.test/admin');
        $result = $this->queue->work(function () use ($other): void { self::assertSame(0, $other->work(fn () => self::fail('Reserva duplicada.'))['sent']); });
        self::assertSame(1, $result['sent']);
    }
    public function testFailureBackoffAndAttemptLimitDoNotLeakExceptions(): void
    {
        $this->queue->enqueue('user@example.test', $this->token);
        $sender = fn () => throw new \RuntimeException('senha-nao-deve-aparecer');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertSame(1, $this->queue->work($sender)['failed']);
            self::assertSame(0, $this->queue->work($sender)['failed']);
            $this->db->exec('UPDATE fx_admin_mail SET available=0');
        }
        self::assertSame(['pending' => 0, 'failed' => 1, 'expired' => 0], $this->queue->status());
        self::assertSame(0, $this->queue->work(fn () => self::fail('Tentativas excedidas.'))['sent']);
    }
    public function testReplacementDuringDeliveryIsNotDeletedByOldWorker(): void
    {
        $this->queue->enqueue('user@example.test', $this->token);
        $this->queue->work(function (): void { $this->queue->enqueue('user@example.test', bin2hex(random_bytes(32))); }, 1);
        self::assertSame(1, $this->queue->status()['pending']);
    }
    public function testUnsafeOriginAndWeakKeyAreRejected(): void
    {
        foreach ([['short', 'https://example.test/admin'], [$this->key, 'http://example.test/admin'], [$this->key, 'https://user:pass@example.test/admin'], [$this->key, 'https://example.test/admin?redirect=evil']] as [$key, $url]) {
            try { new ResetMailQueue($this->db, $key, $url); self::fail('Configuracao invalida aceita.'); }
            catch (\RuntimeException $error) { self::assertNotEmpty($error->getMessage()); }
        }
    }
    public function testCliInitAndStatusDoNotRequireSmtpCredentials(): void
    {
        $root = sys_get_temp_dir() . '/fx-mail-cli-' . bin2hex(random_bytes(8));
        mkdir($root . '/config', 0775, true);
        $dbPath = $root . '/admin.sqlite';
        $db = new \PDO('sqlite:' . $dbPath); $db = null;
        file_put_contents($root . '/config/admin.php', '<?php return ' . var_export(['database' => $dbPath, 'mail' => ['key' => $this->key, 'admin_url' => 'https://example.test/admin']], true) . ';');
        try {
            $app = new \Fx\Framework\Console\Artisan($root); $app->setAutoExit(false);
            foreach (['--init', '--status'] as $option) {
                $output = new \Symfony\Component\Console\Output\BufferedOutput();
                self::assertSame(0, $app->run(new \Symfony\Component\Console\Input\ArrayInput(['command' => 'admin:mail', $option => true]), $output), $output->fetch());
            }
        } finally { unlink($root . '/config/admin.php'); rmdir($root . '/config'); unlink($dbPath); rmdir($root); }
    }
}
