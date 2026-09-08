<?php

declare(strict_types=1);

namespace Fx\Framework\WordPress;

use Fx\Framework\Foundation\CoreApplication;
use InvalidArgumentException;
use RuntimeException;

/** Adaptacao ao host: nao cria kernel, sessao ou conexao de banco. */
final class Plugin
{
    private readonly CoreApplication $app;

    public function __construct(string $pluginFile, private readonly string $slug)
    {
        if (!function_exists('add_action')) { throw new RuntimeException('Inicialize o adaptador dentro do WordPress.'); }
        if (preg_match('/^[a-z][a-z0-9-]*$/D', $slug) !== 1) {
            throw new InvalidArgumentException('Use um slug unico com letras minusculas, numeros e hifens.');
        }
        $this->app = new CoreApplication(dirname($pluginFile));
        $this->app->instance(self::class, $this);
        $this->app->config()->set('plugin.slug', $slug);
    }

    public function app(): CoreApplication { return $this->app; }

    public function action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        \add_action($hook, $callback, $priority, $acceptedArgs);
    }

    public function filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        \add_filter($hook, $callback, $priority, $acceptedArgs);
    }

    /** Permissao obrigatoria, avaliada pelo WordPress antes de executar o endpoint.
     * @param array<string,array<string,mixed>> $args Schema nativo de argumentos REST.
     */
    public function rest(string $route, string $methods, callable $callback, callable $permission, array $args = []): void
    {
        $register = function () use ($route, $methods, $callback, $permission, $args): void {
            \register_rest_route($this->slug . '/v1', '/' . ltrim($route, '/'), [
                'methods' => $methods,
                'callback' => $callback,
                'permission_callback' => $permission,
                'args' => $args,
            ]);
        };
        if (\did_action('rest_api_init')) { $register(); }
        else { $this->action('rest_api_init', $register); }
    }

    /** Nao armazena o usuario: acompanha mudancas feitas pelo host. */
    public function user(): \WP_User { return \wp_get_current_user(); }

    public function can(string $capability, mixed ...$args): bool
    {
        return \current_user_can($capability, ...$args);
    }

    public function permission(string $capability, mixed ...$args): \Closure
    {
        return fn (): bool => $this->can($capability, ...$args);
    }

    public function database(): \wpdb
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) { throw new RuntimeException('A conexao wpdb nao esta disponivel.'); }
        return $wpdb;
    }

    public function option(string $key, mixed $default = false): mixed
    {
        return \get_option($this->optionName($key), $default);
    }

    public function updateOption(string $key, mixed $value): bool
    {
        return \update_option($this->optionName($key), $value, false);
    }

    private function optionName(string $key): string
    {
        if (preg_match('/^[a-z][a-z0-9_-]{0,79}$/D', $key) !== 1) {
            throw new InvalidArgumentException('Chave de opcao invalida.');
        }
        return $this->slug . ':' . $key;
    }
}
