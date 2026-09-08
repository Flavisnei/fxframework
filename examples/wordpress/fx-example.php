<?php
/**
 * Plugin Name: FX Example
 * Description: Configuracao por REST/JSON usando o usuario e permissoes do WordPress.
 * Version: 0.1.0
 * Requires PHP: 8.1
 * Requires at least: 6.4
 */

declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

if (!is_file(__DIR__ . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        if (current_user_can('activate_plugins')) {
            echo '<div class="notice notice-error"><p>FX Example: execute composer install na pasta do exemplo antes de instalar o plugin.</p></div>';
        }
    });
    return;
}
require_once __DIR__ . '/vendor/autoload.php';

add_action('plugins_loaded', static function (): void {
    $plugin = new \Fx\Framework\WordPress\Plugin(__FILE__, 'fx-example');
    $plugin->app()->boot();
    $plugin->rest('/settings', 'POST', static function (\WP_REST_Request $request) use ($plugin): array {
        $name = (string) $request->get_param('name');
        $plugin->updateOption('name', $name);
        return ['name' => (string) $plugin->option('name', '')];
    }, $plugin->permission('manage_options'), [
        'name' => ['type' => 'string', 'required' => true, 'minLength' => 1, 'maxLength' => 80,
            'validate_callback' => 'rest_validate_request_arg',
            'sanitize_callback' => static function (mixed $value): string|\WP_Error {
                if (!is_string($value)) { return new \WP_Error('fx_invalid_name', 'Informe um texto.'); }
                $name = sanitize_text_field($value);
                return $name !== '' ? $name : new \WP_Error('fx_empty_name', 'Informe um nome.');
            }],
    ]);
    $plugin->action('admin_menu', static function () use ($plugin): void {
        add_options_page('FX Example', 'FX Example', 'manage_options', 'fx-example', static function () use ($plugin): void {
            if (!$plugin->can('manage_options')) { wp_die('Acesso negado.'); }
            echo '<div class="wrap"><h1>FX Example</h1><p>Configuração salva usando a REST API do WordPress.</p>';
            echo '<form id="fx-example-form"><label for="fx-example-name">Nome do projeto</label> ';
            echo '<input id="fx-example-name" name="name" required maxlength="80" value="' . esc_attr((string) $plugin->option('name', '')) . '"> ';
            echo '<button class="button button-primary" type="submit">Salvar</button></form>';
            echo '<p id="fx-example-status" role="status" aria-live="polite"></p>';
            echo '<p><a href="' . esc_url(plugins_url('help.html', __FILE__)) . '">Ajuda do exemplo</a></p></div>';
        });
    });
    $plugin->action('admin_enqueue_scripts', static function (string $hook): void {
        if ($hook !== 'settings_page_fx-example') { return; }
        wp_enqueue_script('fx-example-form', plugins_url('form.js', __FILE__), [], '0.1.0', true);
        wp_add_inline_script('fx-example-form', 'window.FxExampleSettings = ' . wp_json_encode([
            'url' => rest_url('fx-example/v1/settings'), 'nonce' => wp_create_nonce('wp_rest'),
        ]) . ';', 'before');
    });
});
