<?php

declare(strict_types=1);

// Execute apenas em WordPress descartavel, com marcador criado durante a preparacao.
$root = getenv('FX_WP_TEST_ROOT');
if (!$root || !is_file($root . '/.fx-test-environment')) {
    throw new RuntimeException('Defina FX_WP_TEST_ROOT para uma instalacao descartavel com .fx-test-environment.');
}
$hostAutoload = getenv('FX_WP_HOST_AUTOLOAD');
if ($hostAutoload) {
    require $hostAutoload;
    // Força as classes do hospedeiro antes de registrar o autoloader FX.
    class_exists(Illuminate\Container\Container::class);
    $hostVersion = Composer\InstalledVersions::getPrettyVersion('illuminate/container');
}
require dirname(__DIR__, 2) . '/examples/wordpress/vendor/autoload.php';
$host = new Illuminate\Container\Container();
Illuminate\Container\Container::setInstance($host);
$session = session_status();
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST']=getenv('FX_WP_TEST_HOST') ?: 'fx-matrix.test';
    $_SERVER['SERVER_NAME']=getenv('FX_WP_TEST_HOST') ?: 'fx-matrix.test';
    $_SERVER['REQUEST_URI']='/';
}
require $root . '/wp-load.php';

$checks = 0;
function verify(bool $result, string $message): void
{
    global $checks;
    $checks++;
    if (!$result) { throw new RuntimeException($message); }
}

use Fx\Framework\WordPress\Plugin;

$first = new Plugin(__FILE__, 'fx-test-first');
$second = new Plugin(__FILE__, 'fx-test-second');
$first->app()->config()->set('test.value', 'first');
$second->app()->config()->set('test.value', 'second');
verify($first->app() !== $second->app(), 'Containers compartilhados.');
verify($first->app()->config()->get('test.value') === 'first', 'Configuracao vazou.');
verify(Illuminate\Container\Container::getInstance() === $host, 'Container hospedeiro alterado.');
verify(session_status() === $session, 'Sessao PHP iniciada.');
verify($first->database() === $GLOBALS['wpdb'], 'Conexao substituida.');
verify(!class_exists(Fx\Framework\Http\Kernel::class), 'Kernel FX instalado.');
verify(!class_exists(Illuminate\Database\Capsule\Manager::class), 'Eloquent instalado.');
verify(!class_exists(Smarty::class), 'Smarty instalado.');

$events = [];
add_action('fx_test_event', static function ($value) use (&$events): void { $events[] = 'native:' . $value; });
$first->action('fx_test_event', static function ($value) use (&$events): void { $events[] = 'first:' . $value; });
$second->action('fx_test_event', static function ($value) use (&$events): void { $events[] = 'second:' . $value; });
do_action('fx_test_event', 'ok');
verify($events === ['native:ok', 'first:ok', 'second:ok'], 'Hooks nao coexistem.');
$first->filter('fx_test_filter', fn ($value, $suffix) => $value . $suffix, 10, 2);
verify(apply_filters('fx_test_filter', 'a', 'b') === 'ab', 'Argumentos do filtro incorretos.');
$first->updateOption('name', 'primeiro');
$second->updateOption('name', 'segundo');
verify($first->option('name') === 'primeiro' && $second->option('name') === 'segundo', 'Opcoes colidiram.');

$admin = get_user_by('login', 'fxtestadmin') ?: get_user_by('login', 'fx_review_admin');
$subscriber = get_user_by('login', 'fxtestsubscriber') ?: get_user_by('login', 'fx_review_subscriber');
verify($admin instanceof WP_User && $subscriber instanceof WP_User, 'Usuarios de teste ausentes.');
wp_set_current_user($subscriber->ID);
verify(!$first->can('manage_options'), 'Assinante recebeu permissao administrativa.');
wp_set_current_user($admin->ID);
verify($first->user()->ID === $admin->ID && $first->can('manage_options'), 'Usuario atual ficou em cache.');
$first->rest('/status', 'GET', fn () => ['plugin' => 'first'], $first->permission('manage_options'));
$second->rest('/status', 'GET', fn () => ['plugin' => 'second'], $second->permission('manage_options'));
verify(rest_do_request('/fx-test-first/v1/status')->get_data()['plugin'] === 'first', 'Rota first incorreta.');
verify(rest_do_request('/fx-test-second/v1/status')->get_data()['plugin'] === 'second', 'Rota second incorreta.');

function settingsRequest(mixed $name): WP_REST_Request
{
    $request = new WP_REST_Request('POST', '/fx-example/v1/settings');
    $request->set_header('Content-Type', 'application/json');
    $request->set_body(json_encode(['name' => $name]));
    return $request;
}
wp_set_current_user(0);
verify(rest_do_request(settingsRequest('Negado'))->get_status() === 401, 'Anonimo salvou configuracao.');
wp_set_current_user($subscriber->ID);
verify(rest_do_request(settingsRequest('Negado'))->get_status() === 403, 'Assinante salvou configuracao.');
wp_set_current_user($admin->ID);
verify(rest_do_request(settingsRequest(['array']))->get_status() === 400, 'Tipo invalido aceito.');
verify(rest_do_request(settingsRequest(str_repeat('a', 81)))->get_status() === 400, 'Limite de tamanho ignorado.');
verify(rest_do_request(settingsRequest(''))->get_status() === 400, 'Texto vazio aceito.');
verify(rest_do_request(settingsRequest('   '))->get_status() === 400, 'Espacos aceitos como nome.');
$response = rest_do_request(settingsRequest('<b>Projeto FX</b>'));
verify($response->get_status() === 200 && $response->get_data()['name'] === 'Projeto FX', 'JSON nao foi sanitizado/salvo.');
verify(get_option('fx-example:name') === 'Projeto FX', 'Opcao nao persistiu.');

// rest_do_request e interno: testar tambem a verificacao nativa de cookie/nonce.
$GLOBALS['wp_rest_auth_cookie'] = true;
$_REQUEST['_wpnonce'] = 'invalid';
$error = rest_cookie_check_errors(null);
verify(is_wp_error($error) && $error->get_error_data()['status'] === 403, 'Nonce invalido aceito.');
$_REQUEST['_wpnonce'] = wp_create_nonce('wp_rest');
verify(rest_cookie_check_errors(null) === true, 'Nonce valido rejeitado.');
unset($_REQUEST['_wpnonce']);
rest_cookie_check_errors(null);
verify(get_current_user_id() === 0, 'Ausencia de nonce manteve usuario autenticado.');
verify(session_status() === $session, 'Adapter iniciou sessao durante operacoes.');
if (is_multisite()) {
    wp_set_current_user($subscriber->ID);
    $originalBlog = get_current_blog_id();
    $sites = get_sites(['site__not_in'=>[$originalBlog], 'number'=>1]);
    verify(count($sites) === 1, 'Crie um segundo site descartavel.');
    $first->updateOption('multisite', 'original');
    switch_to_blog((int)$sites[0]->blog_id);
    try {
        verify($first->option('multisite', 'absent') === 'absent', 'Opcao vazou entre sites.');
        $first->updateOption('multisite', 'second');
        verify($first->option('multisite') === 'second', 'Opcao do segundo site nao persistiu.');
        verify(!$first->can('manage_options'), 'Usuario recebeu permissao no segundo site.');
        add_user_to_blog(get_current_blog_id(), $subscriber->ID, 'administrator');
        wp_set_current_user(0); wp_set_current_user($subscriber->ID);
        verify($first->can('manage_options'), 'Permissao nao acompanhou papel no site.');
        verify($first->database() === $GLOBALS['wpdb'], 'Adapter substituiu wpdb ao trocar site.');
    } finally { restore_current_blog(); }
    verify($first->option('multisite') === 'original', 'Retorno ao site original perdeu opcao.');
    verify(!$first->can('manage_options'), 'Permissao administrativa vazou para site original.');
    verify(Illuminate\Container\Container::getInstance() === $host, 'Multisite alterou container hospedeiro.');
}

$containerVersion = $hostVersion ?? Composer\InstalledVersions::getPrettyVersion('illuminate/container');
echo 'Illuminate container: '.$containerVersion.PHP_EOL;
echo "WordPress {$GLOBALS['wp_version']}: {$checks} verificacoes reais passaram.\n";
