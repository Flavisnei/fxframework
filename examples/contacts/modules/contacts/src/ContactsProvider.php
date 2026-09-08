<?php
declare(strict_types=1);
namespace Example\Contacts;

use Fx\Framework\Admin\Panel;
use Fx\Framework\Http\Request;
use Fx\Framework\Routing\Router;
use Fx\Framework\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

final class ContactsProvider extends ServiceProvider
{
    public function register(): void
    {
        $store = ContactStore::configured($this->app->basePath());
        $router = $this->app->make(Router::class); $panel = $this->app->make(Panel::class);
        $panel->addArea('contacts', 'Contatos', '/contacts', 'contacts.view');
        $panel->api($router, 'GET', 'contacts', 'contacts.view', fn (Request $request) => $store->listing($request->query->all()));
        $panel->api($router, 'POST', 'contacts/read', 'contacts.view', fn ($request, $data) => $store->get(ContactStore::positiveId($data['id'] ?? null)));
        $panel->api($router, 'POST', 'contacts', 'contacts.create', fn ($request, $data) => $store->save(null, $data));
        $panel->api($router, 'PUT', 'contacts', 'contacts.update', fn ($request, $data) => $store->save(ContactStore::positiveId($data['id'] ?? null), $data));
        $panel->api($router, 'DELETE', 'contacts', 'contacts.delete', fn ($request, $data) => $store->delete(ContactStore::positiveId($data['id'] ?? null), ContactStore::positiveId($data['version'] ?? null, 'version')));
        foreach (['/contacts' => ['resources/index.html','text/html'], '/contacts/app.js' => ['resources/app.js','text/javascript'], '/contacts/help' => ['docs/index.html','text/html']] as $uri => [$file,$type]) {
            $router->get($uri, fn () => new Response(file_get_contents(dirname(__DIR__) . '/' . $file), 200, ['Content-Type' => $type . '; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'SAMEORIGIN']));
        }
    }
}
