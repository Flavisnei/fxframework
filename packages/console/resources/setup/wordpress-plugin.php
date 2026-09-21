<?php
/**
 * Plugin Name: FX Projeto
 * Description: Base modular integrada ao WordPress.
 * Version: 0.1.0
 * Requires PHP: 8.1
 */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';

if (! is_file($autoload)) {
    add_action('admin_notices', static function (): void {
        if (current_user_can('activate_plugins')) {
            echo '<div class="notice notice-error"><p>FX Projeto: instale as dependencias com Composer na pasta do plugin.</p></div>';
        }
    });
    return;
}

require_once $autoload;

add_action('plugins_loaded', static function (): void {
    (new App\Plugin(__FILE__))->boot();
});
