<?php
declare(strict_types=1);
namespace Fx\Framework\Tests;

use Fx\Framework\Admin\AdminSession;
use Fx\Framework\Admin\AdminStore;
use Fx\Framework\Admin\Panel;
use Fx\Framework\Foundation\Application;
use Fx\Framework\Http\Request;
use Fx\Framework\Http\Kernel;
use Fx\Framework\Modules\ModuleManager;
use Fx\Framework\Routing\Router;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    use DatabaseBackend;
    private AdminStore $store;
    private \PDO $db;
    private Application $app;
    private AdminSession $session;
    private string $csrf;
    private array $deliveries = [];
    private const PASSWORD = 'initial secret 123';

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        session_id('');
        $this->session = new AdminSession(false);
        $this->session->start();
        $_SESSION = [];
        $this->db = $this->backend();
        $this->store = new AdminStore($this->db);
        $this->store->install('Admin', 'admin@example.test', self::PASSWORD);
        $this->app = new Application(__DIR__);
        (new Panel($this->store, $this->session, new ModuleManager(__DIR__), function (string $email, string $token): void { $this->deliveries[] = [$email, $token]; }))->mount($this->app->make(Router::class));
        $this->csrf = $this->body($this->request('GET', 'session'))['csrf'];
    }
    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
    }
    private function request(string $method, string $path, ?array $data = null, bool $csrf = true): \Symfony\Component\HttpFoundation\Response
    {
        return $this->app->make(Kernel::class)->handle(Request::create('/admin/api/' . $path, $method, [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf ? ($this->csrf ?? '') : ''], $data === null ? null : json_encode($data)));
    }
    private function body($response): array { return json_decode($response->getContent(), true, 32, JSON_THROW_ON_ERROR); }
    private function login(string $email = 'admin@example.test', string $password = self::PASSWORD): void
    {
        $response = $this->request('POST', 'login', ['email' => $email, 'password' => $password]);
        self::assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->csrf = $this->body($response)['csrf'];
    }
    public function testLoginRotatesSessionAndCsrfAndDoesNotExposeHash(): void
    {
        $oldId = session_id(); $oldToken = $this->csrf;
        $this->login();
        self::assertNotSame($oldId, session_id()); self::assertNotSame($oldToken, $this->csrf);
        $response = $this->request('GET', 'users');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('password', $response->getContent());
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
    public function testCsrfAndAnonymousAccessAreDenied(): void
    {
        self::assertSame(403, $this->request('POST', 'login', ['email' => 'admin@example.test', 'password' => self::PASSWORD], false)->getStatusCode());
        self::assertSame(401, $this->request('GET', 'users')->getStatusCode());
        self::assertSame(401, $this->request('POST', 'roles', ['name' => 'Unsafe', 'permissions' => []])->getStatusCode());
    }
    public function testReadOnlyRoleCannotWriteEvenByDirectApi(): void
    {
        $role = $this->store->saveRole(null, ['name' => 'Leitura', 'permissions' => ['dashboard.view', 'users.view']]);
        $this->store->saveUser(null, ['name' => 'Leitor', 'email' => 'reader@example.test', 'password' => self::PASSWORD, 'role_id' => $role]);
        $this->login('reader@example.test');
        self::assertSame(200, $this->request('GET', 'users')->getStatusCode());
        self::assertSame(403, $this->request('POST', 'users', ['name' => 'Invasao'])->getStatusCode());
        self::assertSame(403, $this->request('POST', 'roles', ['name' => 'Elevado', 'permissions' => AdminStore::PERMISSIONS])->getStatusCode());
        self::assertSame(403, $this->request('GET', 'modules')->getStatusCode());
    }
    public function testLastAdministratorAndReservedRoleAreProtected(): void
    {
        $this->login();
        $response = $this->request('POST', 'users', ['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.test', 'role_id' => 1, 'active' => false]);
        self::assertSame(409, $response->getStatusCode());
        self::assertNotNull($this->store->retrieveById(1));
        self::assertSame(409, $this->request('POST', 'roles', ['id' => 1, 'name' => 'Apagado', 'permissions' => []])->getStatusCode());
    }
    public function testAccountChangesInvalidateExistingSession(): void
    {
        $role = $this->store->saveRole(null, ['name' => 'Leitura', 'permissions' => ['users.view']]);
        $id = $this->store->saveUser(null, ['name' => 'Leitor', 'email' => 'reader@example.test', 'password' => self::PASSWORD, 'role_id' => $role]);
        $this->login('reader@example.test');
        $this->store->saveUser($id, ['name' => 'Leitor', 'email' => 'reader@example.test', 'role_id' => $role, 'active' => false]);
        self::assertSame(401, $this->request('GET', 'users')->getStatusCode());
    }
    public function testPermissionsAreReadAgainAfterRoleUpdate(): void
    {
        $role = $this->store->saveRole(null, ['name' => 'Operador', 'permissions' => ['users.view']]);
        $this->store->saveUser(null, ['name' => 'Operador', 'email' => 'operator@example.test', 'password' => self::PASSWORD, 'role_id' => $role]);
        $this->login('operator@example.test');
        $this->store->saveRole($role, ['name' => 'Operador', 'permissions' => []]);
        self::assertSame(403, $this->request('GET', 'users')->getStatusCode());
    }
    public function testRecoveryIsGenericAndTokenIsHashedSingleUseAndInvalidatesSession(): void
    {
        $this->login();
        $known = $this->request('POST', 'forgot', ['email' => 'admin@example.test']);
        $unknown = $this->request('POST', 'forgot', ['email' => 'missing@example.test']);
        self::assertSame($known->getContent(), $unknown->getContent());
        self::assertCount(1, $this->deliveries);
        $token = $this->deliveries[0][1];
        self::assertNotSame($token, $this->db->query('SELECT token FROM fx_admin_resets')->fetchColumn());
        self::assertStringNotContainsString($token, $known->getContent());
        $this->store->resetPassword($token, 'new secret password');
        self::assertSame(401, $this->request('GET', 'users')->getStatusCode());
        try { $this->store->resetPassword($token, 'another secret pass'); self::fail('Token reutilizado.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { self::assertSame(422, $error->getStatusCode()); }
    }
    public function testExpiredResetAndPersistentThrottle(): void
    {
        $token = $this->store->resetToken($this->store->retrieveById(1));
        $this->db->exec('UPDATE fx_admin_resets SET expires=0');
        self::assertSame(422, $this->request('POST', 'reset', ['token' => $token, 'password' => 'new secret password'])->getStatusCode());
        $this->store->throttle('fixture', 1);
        try { (new AdminStore($this->db))->throttle('fixture', 1); self::fail('Limite nao persistiu.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { self::assertSame(429, $error->getStatusCode()); }
    }
    public function testExpiredSessionAndLogout(): void
    {
        $this->login(); $_SESSION['_fx_admin_meta']['last'] = time() - 1801;
        self::assertSame(401, $this->request('GET', 'users')->getStatusCode());
        $this->csrf = $this->body($this->request('GET', 'session'))['csrf'];
        $this->login();
        self::assertSame(200, $this->request('POST', 'logout', [])->getStatusCode());
        self::assertSame(401, $this->request('GET', 'users')->getStatusCode());
    }
    public function testPasswordWhitespaceIsPreservedAndInstallNeverResetsExistingAccounts(): void
    {
        $this->store->saveUser(null, ['name' => 'Espacos', 'email' => 'spaces@example.test', 'password' => ' secret with spaces ', 'role_id' => 1]);
        $this->login('spaces@example.test', ' secret with spaces ');
        $this->expectExceptionMessage('Admin ja inicializado');
        $this->store->install('Outro', 'other@example.test', 'other password 123');
    }
    public function testPaginationAndDuplicateEmail(): void
    {
        $this->login();
        $response = $this->request('POST', 'users', ['name' => 'Duplicado', 'email' => 'ADMIN@example.test', 'password' => self::PASSWORD, 'role_id' => 1]);
        self::assertSame(409, $response->getStatusCode());
        $data = $this->body($this->request('GET', 'users?page=2'));
        self::assertSame([], $data['data']); self::assertSame(1, $data['total']);
        self::assertSame(422, $this->request('GET', 'users?q[]=invalid')->getStatusCode());
        self::assertSame(422, $this->request('GET', 'users?page[]=invalid')->getStatusCode());
    }

    public function testDelegatedManagerCannotReplaceAdministratorCredentials(): void
    {
        $role = $this->store->saveRole(null, ['name' => 'Delegado', 'permissions' => AdminStore::PERMISSIONS]);
        $this->store->saveUser(null, ['name' => 'Delegado', 'email' => 'delegate@example.test', 'password' => self::PASSWORD, 'role_id' => $role]);
        $this->login('delegate@example.test');
        $response = $this->request('POST', 'users', ['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'replacement secret', 'role_id' => 1]);
        self::assertSame(403, $response->getStatusCode());
        self::assertTrue($this->store->validateCredentials($this->store->retrieveById(1), ['password' => self::PASSWORD]));
        $inactive = $this->store->saveUser(null, ['name' => 'Admin inativo', 'email' => 'inactive@example.test', 'password' => self::PASSWORD, 'role_id' => 1, 'active' => false]);
        self::assertSame(403, $this->request('POST', 'users', ['id' => $inactive, 'name' => 'Admin inativo', 'email' => 'inactive@example.test', 'role_id' => $role, 'active' => true])->getStatusCode());
    }

    public function testLoginEndpointThrottlesRepeatedFailures(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) { self::assertSame(401, $this->request('POST', 'login', ['email' => 'admin@example.test', 'password' => 'incorrect'])->getStatusCode()); }
        $response = $this->request('POST', 'login', ['email' => 'admin@example.test', 'password' => self::PASSWORD]);
        self::assertSame(429, $response->getStatusCode());
        self::assertNotNull($response->headers->get('Retry-After'));
    }
}
