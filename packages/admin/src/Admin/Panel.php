<?php
declare(strict_types=1);
namespace Fx\Framework\Admin;

use Fx\Framework\Http\Csrf;
use Fx\Framework\Http\Request;
use Fx\Framework\Modules\ModuleManager;
use Fx\Framework\Routing\Router;
use Fx\Framework\Windows\Assets;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class Panel
{
    /** delivery(email, token): deve enfileirar a mensagem. logger(evento, contexto): sem segredos. */
    public function __construct(private readonly AdminStore $store, private readonly AdminSession $session, private readonly ModuleManager $modules, private readonly ?\Closure $delivery = null, private readonly ?\Closure $logger = null) {}

    public function mount(Router $router): void
    {
        $resources = dirname(__DIR__, 2) . '/resources';
        foreach (['/admin' => [$resources . '/index.html', 'text/html'], '/admin/admin.js' => [$resources . '/admin.js', 'text/javascript'], '/admin/admin.css' => [$resources . '/admin.css', 'text/css'], '/admin/fxwindows.js' => [Assets::directory() . '/fxwindows.js', 'text/javascript'], '/admin/fxwindows.css' => [Assets::directory() . '/fxwindows.css', 'text/css'], '/admin/help' => [dirname(__DIR__, 2) . '/docs/index.html', 'text/html']] as $uri => [$file, $type]) {
            $router->get($uri, fn () => new Response(file_get_contents($file), 200, ['Content-Type' => $type . '; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer', 'X-Frame-Options' => 'SAMEORIGIN', 'Cache-Control' => 'no-store']));
        }
        $route = function (string $method, string $path, ?string $permission, \Closure $action) use ($router): void {
            $router->add([$method], '/admin/api/' . $path, function (Request $request) use ($permission, $action): Response {
                try {
                    $this->session->start();
                    if (strlen($request->getContent()) > 16384) { throw new HttpException(413, 'Formulario excede o limite de tamanho.'); }
                    if (!$request->isMethodSafe() && !Csrf::validate($request->headers->get('X-CSRF-TOKEN'))) { throw new HttpException(403, 'Sessao do formulario expirou. Recarregue a pagina.'); }
                    $user = $this->session->user($this->store);
                    if ($permission !== null) {
                        if ($user === null) { throw new HttpException(401, 'Entre para continuar.'); }
                        $permissions = $this->store->permissions($user);
                        if (!in_array('dashboard.view', $permissions, true) || !in_array($permission, $permissions, true)) { throw new HttpException(403, 'Acesso negado.'); }
                    }
                    $data = $request->isMethodSafe() ? [] : json_decode($request->getContent(), true, 32, JSON_THROW_ON_ERROR);
                    if (!is_array($data)) { throw new HttpException(422, 'Envie um objeto JSON.'); }
                    $result = $action($request, $data, $user);
                    return new JsonResponse($result, 200, ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
                } catch (\Throwable $error) {
                    $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : ($error instanceof \JsonException ? 422 : 500);
                    if ($status >= 500) { $this->log('admin.error', ['type' => $error::class]); }
                    return new JsonResponse(['message' => $status >= 500 ? 'Falha interna. Consulte o responsavel pelo sistema.' : ($error instanceof \JsonException ? 'JSON invalido.' : $error->getMessage())], $status, ['Cache-Control' => 'no-store'] + ($error instanceof HttpExceptionInterface ? $error->getHeaders() : []));
                }
            });
        };
        $route('GET', 'session', null, fn ($request, $data, $user) => ['csrf' => Csrf::token(), 'user' => $user?->publicData(), 'permissions' => $user ? $this->store->permissions($user) : [], 'recovery' => $this->delivery !== null]);
        $route('POST', 'login', null, function (Request $request, array $data): array {
            $email = strtolower(AdminStore::text($data, 'email', 3, 254));
            $password = $data['password'] ?? null;
            if (!is_string($password) || strlen($password) < 1 || strlen($password) > 72 || str_contains($password, "\0")) { throw new HttpException(422, 'Senha invalida.'); }
            $this->store->throttle('login-ip:' . $request->server->get('REMOTE_ADDR', 'unknown'), 50);
            $this->store->throttle('login-email:' . $email, 10);
            $user = $this->store->retrieveByCredentials(['email' => $email]);
            if (!$user instanceof AdminUser || !$this->store->validateCredentials($user, ['password' => $password])) {
                if ($user === null) { password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi'); }
                $this->log('login.failed', []);
                throw new HttpException(401, 'Email ou senha invalidos.');
            }
            $this->session->login($this->store, $user);
            $this->log('login.success', ['user_id' => $user->getAuthIdentifier()]);
            return ['csrf' => Csrf::token()];
        });
        $route('POST', 'logout', null, function (): array { $this->session->logout($this->store); return ['csrf' => Csrf::token()]; });
        $route('POST', 'forgot', null, function (Request $request, array $data): array {
            if ($this->delivery === null) { throw new HttpException(503, 'Recuperacao ainda nao configurada.'); }
            $email = strtolower(AdminStore::text($data, 'email', 3, 254));
            $this->store->throttle('forgot-ip:' . $request->server->get('REMOTE_ADDR', 'unknown'), 20);
            $this->store->throttle('forgot-email:' . $email, 3);
            $user = $this->store->retrieveByCredentials(['email' => $email]);
            if ($user instanceof AdminUser) {
                try { ($this->delivery)($email, $this->store->resetToken($user)); }
                catch (\Throwable $error) { $this->log('recovery.delivery_failed', ['type' => $error::class]); }
            }
            return ['message' => 'Se a conta estiver disponivel, voce recebera as instrucoes.'];
        });
        $route('POST', 'reset', null, function (Request $request, array $data): array {
            $this->store->throttle('reset-ip:' . $request->server->get('REMOTE_ADDR', 'unknown'), 20);
            $token = AdminStore::text($data, 'token', 64, 64);
            $password = $data['password'] ?? null;
            if (!is_string($password)) { throw new HttpException(422, 'Senha invalida.'); }
            $this->store->resetPassword($token, $password);
            $this->session->logout($this->store);
            $this->log('recovery.completed', []);
            return ['message' => 'Senha alterada. Entre novamente.', 'csrf' => Csrf::token()];
        });
        $route('GET', 'users', 'users.view', function (Request $request): array {
            $query = $request->query->all();
            $page = $query['page'] ?? '1'; $search = $query['q'] ?? '';
            if (!is_scalar($page) || !ctype_digit((string) $page) || !is_string($search)) { throw new HttpException(422, 'Pesquisa ou pagina invalida.'); }
            return $this->store->users((int) $page, $search);
        });
        $route('POST', 'users', 'users.manage', function ($request, array $data, AdminUser $actor): array {
            $id = $data['id'] ?? null;
            if ($id !== null && (!is_int($id) || $id < 1)) { throw new HttpException(422, 'ID invalido.'); }
            // Administradores delegados nao podem substituir credenciais do perfil reservado.
            $existingRole = $id === null ? null : $this->store->accountRole($id);
            if ((int) $actor->data['role_id'] !== 1 && (($data['role_id'] ?? null) === 1 || $existingRole === 1)) { throw new HttpException(403, 'Somente administradores podem alterar contas do perfil reservado.'); }
            $saved = $this->store->saveUser($id, $data);
            $this->log('user.saved', ['actor' => $actor->getAuthIdentifier(), 'target' => $saved]);
            return ['id' => $saved];
        });
        $route('GET', 'roles', 'dashboard.view', fn () => ['data' => $this->store->roles(), 'permissions' => AdminStore::PERMISSIONS]);
        $route('POST', 'roles', 'roles.manage', function ($request, array $data, AdminUser $actor): array {
            $id = $data['id'] ?? null;
            if ($id !== null && (!is_int($id) || $id < 1)) { throw new HttpException(422, 'ID invalido.'); }
            $saved = $this->store->saveRole($id, $data);
            $this->log('role.saved', ['actor' => $actor->getAuthIdentifier(), 'target' => $saved]);
            return ['id' => $saved];
        });
        $route('GET', 'modules', 'modules.view', function (): array {
            $enabled = $this->modules->enabled();
            return ['data' => array_map(fn ($definition) => ['id' => $definition->id, 'version' => $definition->version, 'enabled' => isset($enabled[$definition->id])], array_values($this->modules->definitions()))];
        });
        $route('POST', 'modules', 'modules.manage', function ($request, array $data, AdminUser $actor): array {
            $id = AdminStore::text($data, 'id', 1, 100);
            if ($id === 'fx-admin') { throw new HttpException(409, 'Gerencie o proprio painel pelo CLI.'); }
            if (!is_bool($data['enabled'] ?? null)) { throw new HttpException(422, 'enabled deve ser booleano.'); }
            try { $data['enabled'] ? $this->modules->enable($id) : $this->modules->disable($id); }
            catch (\RuntimeException $error) { throw new HttpException(409, 'Nao foi possivel alterar. Consulte module:doctor no CLI.', $error); }
            $this->log('module.changed', ['actor' => $actor->getAuthIdentifier(), 'module' => $id]);
            return ['message' => 'Estado atualizado para o proximo bootstrap.'];
        });
    }
    private function log(string $event, array $context): void
    {
        if ($this->logger !== null) { try { ($this->logger)($event, $context); } catch (\Throwable) { error_log('FX Admin: falha no logger.'); } }
        elseif ($event === 'admin.error' || $event === 'recovery.delivery_failed') { error_log('FX Admin: ' . $event); }
    }
}
