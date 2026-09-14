<?php
/**
 * Plugin Name: FX Projeto
 * Description: Base modular integrada ao WordPress.
 * Version: 0.1.0
 * Requires PHP: 8.1
 */
declare(strict_types=1);
if(!defined('ABSPATH'))exit;
if(!is_file(__DIR__.'/vendor/autoload.php')) {
    add_action('admin_notices',static function():void {if(current_user_can('activate_plugins'))echo '<div class="notice notice-error"><p>FX Projeto: instale as dependencias com Composer na pasta do plugin.</p></div>';});
    return;
}
require_once __DIR__.'/vendor/autoload.php';
add_action('plugins_loaded',static function():void {
    $id='fx-'.substr(hash('sha256',plugin_basename(__FILE__)),0,12);
    $plugin=new Fx\Framework\WordPress\Plugin(__FILE__,$id);$plugin->app()->boot();
    $plugin->action('admin_menu',static function()use($plugin,$id):void {
        add_management_page('FX Projeto','FX Projeto','manage_options',$id,static function()use($plugin):void {
            if(!$plugin->can('manage_options'))wp_die('Acesso negado.');
            echo '<div class="wrap"><h1>FX Projeto</h1><p>Integracao ativa. Use as APIs de usuarios, permissoes e banco do WordPress.</p></div>';
        });
    });
});
