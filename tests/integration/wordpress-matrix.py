"""Instala WordPress descartável e testa o adaptador; não envia emails."""
import argparse, hashlib, json, os, pathlib, re, secrets, shutil, subprocess, sys, tempfile

root = pathlib.Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--php', default='php')
parser.add_argument('--wp-cli', required=True, help='PHAR oficial do WP-CLI')
parser.add_argument('--version', required=True, help='Versão exata do WordPress')
parser.add_argument('--multisite', action='store_true')
parser.add_argument('--cache-dir', help='Cache opcional de fontes oficiais sem configuração')
parser.add_argument('--host-autoload', help='Autoload de outro plugin, carregado primeiro')
parser.add_argument('--port', type=int, default=3306)
args = parser.parse_args()
if not re.fullmatch(r'[0-9]+\.[0-9]+(?:\.[0-9]+)?', args.version): parser.error('Informe uma versão numérica exata.')
directory = pathlib.Path(tempfile.mkdtemp(prefix='fx-wp-matrix-run-')).resolve()
config = {'database': 'fx_wp_matrix_' + secrets.token_hex(12), 'port': args.port,
          'user': os.getenv('FX_TEST_MYSQL_USER', 'root'), 'password': os.getenv('FX_TEST_MYSQL_PASSWORD', '')}

def database(action):
    result = subprocess.run([args.php, str(root/'tests/integration/wordpress-database.php'), action], input=json.dumps(config), text=True, capture_output=True)
    if result.returncode: raise RuntimeError('Banco temporário: falha ao ' + action)

def wp(*command):
    print('WordPress: '+' '.join(command[:2]), file=sys.stderr, flush=True)
    result = subprocess.run([args.php, str(pathlib.Path(args.wp_cli).resolve()), '--path='+str(directory), *command], capture_output=True, text=True, encoding='utf-8', errors='replace')
    if result.returncode: raise RuntimeError(result.stdout + result.stderr)
    return result.stdout.strip()

def literal(value): return "'" + str(value).replace('\\', '\\\\').replace("'", "\\'") + "'"

try:
    cache = pathlib.Path(args.cache_dir).resolve()/args.version if args.cache_dir else None
    if cache and cache.is_dir():
        if (cache/'wp-config.php').exists(): raise RuntimeError('Cache não pode conter configuração.')
        shutil.copytree(cache, directory, dirs_exist_ok=True)
    else:
        wp('core', 'download', 'https://downloads.wordpress.org/release/wordpress-'+args.version+'.zip')
        if cache: shutil.copytree(directory, cache)
    checksum = wp('core', 'verify-checksums', '--version='+args.version, '--locale=en_US')
    database('create')
    definitions = {'DB_NAME': config['database'], 'DB_USER': config['user'], 'DB_PASSWORD': config['password'],
        'DB_HOST': '127.0.0.1:'+str(args.port), 'DB_CHARSET': 'utf8mb4', 'DB_COLLATE': ''}
    for key in ['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT']:
        definitions[key] = secrets.token_hex(32)
    text = '<?php\n' + '\n'.join('define('+literal(k)+','+literal(v)+');' for k,v in definitions.items())
    text += "\ndefine('DISABLE_WP_CRON',true);\ndefine('AUTOMATIC_UPDATER_DISABLED',true);\n$table_prefix='wp_';\n/* That's all, stop editing! Happy publishing. */\nif (!defined('ABSPATH')) define('ABSPATH',__DIR__.'/');\nrequire_once ABSPATH.'wp-settings.php';\n"
    (directory/'wp-config.php').write_text(text, encoding='utf-8')
    (directory/'.fx-test-environment').touch()
    mu = directory/'wp-content/mu-plugins'; mu.mkdir(exist_ok=True)
    (mu/'no-mail.php').write_text("<?php add_filter('pre_wp_mail','__return_false');", encoding='utf-8')
    wp('core', 'install', '--url=http://fx-matrix.test', '--title=FX Test', '--admin_user=fxtestadmin', '--admin_password='+secrets.token_hex(24), '--admin_email=admin@example.test', '--skip-email')
    wp('user', 'create', 'fxtestsubscriber', 'subscriber@example.test', '--role=subscriber', '--user_pass='+secrets.token_hex(24))
    plugin = directory/'wp-content/plugins/fx-matrix'; plugin.mkdir()
    (plugin/'fx-matrix.php').write_text('<?php /* Plugin Name: FX Matrix */ require '+literal((root/'examples/wordpress/fx-example.php').as_posix())+';', encoding='utf-8')
    wp('plugin', 'activate', 'fx-matrix')
    env = os.environ.copy(); env['FX_WP_TEST_ROOT'] = str(directory); env['FX_WP_TEST_HOST'] = 'fx-matrix.test'
    if args.host_autoload: env['FX_WP_HOST_AUTOLOAD'] = str(pathlib.Path(args.host_autoload).resolve())
    results = []
    for multisite in ([False, True] if args.multisite else [False]):
        if multisite:
            wp('core', 'multisite-convert', '--title=FX Network', '--base=/')
            wp('plugin', 'activate', 'fx-matrix', '--network')
            wp('site', 'create', '--slug=second', '--title=Second', '--email=admin@example.test')
        result = subprocess.run([args.php, str(root/'tests/integration/wordpress.php')], env=env, capture_output=True, text=True, encoding='utf-8', errors='replace')
        if result.returncode: raise RuntimeError(result.stdout + result.stderr)
        results.append({'multisite': multisite, 'output': result.stdout.strip(), 'diagnostics': result.stderr.strip()})
    print(json.dumps({'version': args.version, 'host_autoload_first': bool(args.host_autoload), 'checksums': checksum,
        'results': results, 'wp_cli_sha256': hashlib.sha256(pathlib.Path(args.wp_cli).read_bytes()).hexdigest(),
        'scope': 'WordPress real via CLI/REST interno; email bloqueado por pre_wp_mail; sem revisão visual.'}, ensure_ascii=False, indent=2))
finally:
    try: database('drop')
    finally:
        if directory.parent == pathlib.Path(tempfile.gettempdir()).resolve() and directory.name.startswith('fx-wp-matrix-run-'):
            shutil.rmtree(directory)
