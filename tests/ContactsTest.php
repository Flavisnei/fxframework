<?php
declare(strict_types=1);
namespace Fx\Framework\Tests;

use Example\Contacts\ContactStore;
use Example\Contacts\ContactsProvider;
use Fx\Framework\Admin\{AdminSession, AdminStore, Panel};
use Fx\Framework\Foundation\Application;
use Fx\Framework\Http\{Kernel, Request};
use Fx\Framework\Modules\ModuleManager;
use Fx\Framework\Routing\Router;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/examples/contacts/modules/contacts/src/ContactStore.php';
require_once dirname(__DIR__) . '/examples/contacts/modules/contacts/src/ContactsProvider.php';

final class ContactsTest extends TestCase
{
    use DatabaseBackend;
    private string $root;
    private AdminStore $admin;
    private Application $app;
    private Panel $panel;
    private string $csrf;
    private const PERMISSIONS = ['contacts.view','contacts.create','contacts.update','contacts.delete'];
    private const CONTACT = ['name'=>'Contato Teste','email'=>'contact@example.test','phone'=>'','notes'=>'Nota privada'];

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        session_id(''); $session = new AdminSession(false); $session->start(); $_SESSION = [];
        $this->root = sys_get_temp_dir() . '/fx-contacts-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/storage', 0770, true);
        $store = new ContactStore($this->backend('sqlite:' . $this->root . '/storage/contacts.sqlite')); $store->install();
        if ($this->backendConfig !== null) { mkdir($this->root.'/config'); file_put_contents($this->root.'/config/contacts.php','<?php return '.var_export(['database'=>$this->backendConfig],true).';'); }
        $this->admin = new AdminStore($this->backend(), self::PERMISSIONS);
        $this->admin->install('Admin', 'admin@example.test', 'initial secret 123');
        $this->app = new Application($this->root);
        $this->panel = new Panel($this->admin, $session, new ModuleManager($this->root));
        $this->app->instance(Panel::class, $this->panel);
        $this->panel->mount($this->app->make(Router::class));
        (new ContactsProvider($this->app))->register();
        $this->csrf = $this->call('GET','session')[1]['csrf'];
    }
    protected function tearDown(): void
    {
        $_SESSION = []; if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
        Application::setInstance(null); unset($this->app, $this->panel); gc_collect_cycles();
        if (is_file($this->root.'/config/contacts.php')) { unlink($this->root.'/config/contacts.php');rmdir($this->root.'/config'); }
        if (is_file($this->root . '/storage/contacts.sqlite')) unlink($this->root . '/storage/contacts.sqlite'); rmdir($this->root . '/storage'); rmdir($this->root);
    }
    private function call(string $method, string $path, ?array $data = null, bool $csrf = true): array
    {
        $response = $this->app->make(Kernel::class)->handle(Request::create('/admin/api/' . $path, $method, [], [], [], ['CONTENT_TYPE'=>'application/json','HTTP_X_CSRF_TOKEN'=>$csrf ? ($this->csrf ?? '') : ''], $data === null ? null : json_encode($data)));
        return [$response->getStatusCode(), json_decode($response->getContent(), true)];
    }
    private function login(string $email = 'admin@example.test'): void
    {
        [$status,$result] = $this->call('POST','login',['email'=>$email,'password'=>'initial secret 123']);
        self::assertSame(200,$status); $this->csrf = $result['csrf'];
    }
    public function testCrudAndStaleVersions(): void
    {
        $this->login();
        [$status,$created] = $this->call('POST','contacts',self::CONTACT); self::assertSame(200,$status);
        self::assertSame(1,$created['version']);
        $data = [...self::CONTACT,'id'=>$created['id'],'version'=>1,'name'=>'Nome novo'];
        [$status,$updated] = $this->call('PUT','contacts',$data); self::assertSame(200,$status); self::assertSame(2,$updated['version']);
        self::assertSame(409,$this->call('PUT','contacts',$data)[0]);
        self::assertSame(409,$this->call('DELETE','contacts',['id'=>$created['id'],'version'=>1])[0]);
        self::assertSame('Nome novo',$this->call('POST','contacts/read',['id'=>$created['id']])[1]['name']);
        self::assertSame(200,$this->call('DELETE','contacts',['id'=>$created['id'],'version'=>2])[0]);
        self::assertSame(404,$this->call('POST','contacts/read',['id'=>$created['id']])[0]);
    }
    public function testFieldErrorsAndDuplicateEmail(): void
    {
        $this->login();
        [$status,$body] = $this->call('POST','contacts',['name'=>[],'email'=>'wrong']);
        self::assertSame(422,$status); self::assertArrayHasKey('name',$body['errors']); self::assertArrayHasKey('email',$body['errors']);
        $this->call('POST','contacts',self::CONTACT);
        [$status,$body] = $this->call('POST','contacts',[...self::CONTACT,'email'=>'CONTACT@example.test']);
        self::assertSame(409,$status); self::assertArrayHasKey('email',$body['errors']);
        self::assertSame(422,$this->call('PUT','contacts',[...self::CONTACT,'id'=>'1','version'=>1])[0]);
        self::assertSame(422,$this->call('POST','contacts',['bad','list'])[0]);
    }
    public function testAuthorizationAndCsrfOnEveryVerb(): void
    {
        self::assertSame(401,$this->call('GET','contacts')[0]);
        self::assertSame([],$this->call('GET','session')[1]['areas']);
        $role = $this->admin->saveRole(null,['name'=>'Leitor','permissions'=>['dashboard.view','contacts.view']]);
        $this->admin->saveUser(null,['name'=>'Leitor','email'=>'reader@example.test','password'=>'initial secret 123','role_id'=>$role]);
        $this->login('reader@example.test');
        self::assertSame('contacts',$this->call('GET','session')[1]['areas'][0]['id']);
        self::assertSame(200,$this->call('GET','contacts')[0]);
        foreach (['POST','PUT','DELETE'] as $verb) { self::assertSame(403,$this->call($verb,'contacts',self::CONTACT)[0]); }
        $this->login();
        foreach (['POST','PUT','DELETE'] as $verb) { self::assertSame(403,$this->call($verb,'contacts',self::CONTACT,false)[0]); }
        $this->admin->saveRole($role,['name'=>'Leitor','permissions'=>['contacts.view']]); $this->login('reader@example.test');
        self::assertSame(403,$this->call('GET','contacts')[0]); self::assertSame([],$this->call('GET','session')[1]['areas']);
    }
    public function testPaginationLiteralSearchAndCompactList(): void
    {
        $this->login();
        for ($i=0;$i<21;$i++) $this->call('POST','contacts',[...self::CONTACT,'email'=>"contact$i@example.test"]);
        [$status,$body] = $this->call('GET','contacts'); self::assertSame(200,$status); self::assertCount(20,$body['data']); self::assertSame(21,$body['total']); self::assertArrayNotHasKey('notes',$body['data'][0]);
        self::assertCount(1,$this->call('GET','contacts?page=2')[1]['data']);
        self::assertSame(0,$this->call('GET','contacts?q=%25')[1]['total']);
        self::assertSame(422,$this->call('GET','contacts?page[]=1')[0]);
        self::assertSame(422,$this->call('GET','contacts?q[]=bad')[0]);
    }
    public function testExtensionsAreExplicitAndDoNotGrantOtherRoles(): void
    {
        self::assertContains('contacts.view',$this->admin->permissions($this->admin->retrieveById(1)));
        $role = $this->admin->saveRole(null,['name'=>'Basico','permissions'=>['dashboard.view']]);
        $id = $this->admin->saveUser(null,['name'=>'Basico','email'=>'basic@example.test','password'=>'initial secret 123','role_id'=>$role]);
        self::assertNotContains('contacts.view',$this->admin->permissions($this->admin->retrieveById($id)));
        foreach (['//evil.test','https://evil.test','/bad\\path'] as $url) {
            try { $this->panel->addArea('unsafe','Unsafe',$url,'contacts.view'); self::fail('URL aceita'); } catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
        $this->expectException(\InvalidArgumentException::class); $this->panel->addArea('contacts','Duplicado','/contacts','contacts.view');
    }
}
