"""Carga opt-in do painel no Apache/MariaDB local com dados descartáveis."""
import argparse, concurrent.futures, http.cookiejar, json, math, os, pathlib
import secrets, shutil, subprocess, tempfile, threading, time, urllib.request, urllib.error

root = pathlib.Path(__file__).resolve().parent.parent
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--php', default='php')
parser.add_argument('--webroot', required=True)
parser.add_argument('--clients', type=int, default=4)
parser.add_argument('--cycles', type=int, default=10, help='Cada ciclo: GET, POST, PUT e DELETE')
parser.add_argument('--port', type=int, default=3306)
args = parser.parse_args()
if not 1 <= args.clients <= 16 or not 1 <= args.cycles <= 100 or not 1 <= args.port <= 65535:
    parser.error('Use 1–16 clientes, 1–100 ciclos e porta válida.')
webroot = pathlib.Path(args.webroot).resolve()
if not webroot.is_dir(): parser.error('Webroot do Apache local na porta 80 deve existir.')
run = secrets.token_hex(12)
folder = webroot / ('fx-admin-load-' + run)
config = {'database': 'fx_admin_load_' + run, 'port': args.port, 'clients': args.clients,
          'user': os.getenv('FX_TEST_MYSQL_USER', 'root'), 'password': os.getenv('FX_TEST_MYSQL_PASSWORD', '')}
directory = pathlib.Path(tempfile.mkdtemp(prefix='fx-contacts-review-')).resolve()
config['directory'] = str(directory)

def fixture(action):
    result = subprocess.run([args.php, str(root / 'benchmarks/admin-mariadb-fixture.php'), action],
                            input=json.dumps(config), text=True, capture_output=True, cwd=root)
    if result.returncode: raise RuntimeError('Fixture falhou: ' + action + '; detalhes privados omitidos.')
    return json.loads(result.stdout)

try:
    proof = fixture('create')
    directory = pathlib.Path(proof['directory']).resolve()
    folder.mkdir()
    token = secrets.token_hex(32)
    target = (directory / 'public/index.php').as_posix().replace("'", "\\'")
    (folder / 'index.php').write_text("<?php if (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1' || !hash_equals('" + token + "', $_SERVER['HTTP_X_FX_TEST_TOKEN'] ?? '')) { http_response_code(403); exit; } require '" + target + "';", encoding='utf-8')
    url = 'http://127.0.0.1/' + folder.name + '/index.php'
    started = [None]
    barrier = threading.Barrier(args.clients, action=lambda: started.__setitem__(0, time.perf_counter()), timeout=60)

    def client(index):
        opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        csrf = ''
        def request(method, path, data=None, expected=200):
            req = urllib.request.Request(url + path, method=method, data=None if data is None else json.dumps(data).encode(),
                headers={'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-FX-TEST-TOKEN': token})
            try:
                with opener.open(req, timeout=30) as response: status, raw = response.status, response.read()
            except urllib.error.HTTPError as error: status, raw = error.code, error.read()
            if status != expected: raise RuntimeError('HTTP ' + str(status) + ' esperado ' + str(expected))
            return json.loads(raw)
        try:
            request('GET', '/admin/api/contacts', expected=401)
            csrf = request('GET', '/admin/api/session')['csrf']
            csrf = request('POST', '/admin/api/login', {'email': f'client{index}@example.test', 'password': proof['password']})['csrf']
            page = request('GET', '/admin/api/contacts')
            if page['total'] != 1000 or len(page['data']) != 20: raise RuntimeError('Aquecimento inválido.')
        except Exception:
            barrier.abort()
            raise
        barrier.wait()
        timings = {method: [] for method in ['GET', 'POST', 'PUT', 'DELETE']}
        errors = []
        for n in range(args.cycles):
            data = {'name': f'Cliente {index}', 'email': f'load{index}-{n}@example.test', 'phone': '', 'notes': 'Carga descartavel'}
            try:
                for method in timings:
                    start = time.perf_counter()
                    result = request(method, '/admin/api/contacts', None if method == 'GET' else data)
                    if method == 'GET' and len(result['data']) != 20: raise RuntimeError('Página inválida.')
                    if method in ['POST', 'PUT']:
                        expected_version = 1 if method == 'POST' else 2
                        if result['version'] != expected_version: raise RuntimeError('Versão inválida.')
                        data.update(id=result['id'], version=result['version'], name='Contato atualizado')
                    timings[method].append((time.perf_counter() - start) * 1000)
            except Exception as error: errors.append(str(error))
        request('POST', '/admin/api/logout', {})
        request('GET', '/admin/api/contacts', expected=401)
        return timings, errors

    with concurrent.futures.ThreadPoolExecutor(max_workers=args.clients) as pool:
        results = list(pool.map(client, range(args.clients)))
    elapsed = time.perf_counter() - started[0]
    final = fixture('inspect')
    metrics = {}
    for method in ['GET', 'POST', 'PUT', 'DELETE']:
        values = sorted(value for row in results for value in row[0][method])
        metrics[method] = {'successes': len(values), 'p50_ms': values[math.ceil(.5*len(values))-1] if values else None,
                           'p95_ms': values[math.ceil(.95*len(values))-1] if values else None}
    errors = [error for row in results for error in row[1]]
    integrity = final == {'rows': 1000, 'changed_seed': 0} and all(m['successes'] == args.clients*args.cycles for m in metrics.values())
    print(json.dumps({'schema': 1, 'php_cli': proof['php'], 'mariadb': proof['mariadb'], 'server': 'Apache local',
        'clients': args.clients, 'cycles_per_client': args.cycles, 'metrics': metrics, 'errors': errors,
        'elapsed_seconds_including_logout': elapsed, 'requests_per_second': sum(m['successes'] for m in metrics.values())/elapsed,
        'final': final, 'integrity': integrity, 'scope': 'Sessões distintas, CSRF, CRUD JSON, 1000 contatos; loopback em um nó; não certifica capacidade de produção.'}, ensure_ascii=False, indent=2))
    if errors or not integrity: raise SystemExit(1)
finally:
    if folder.parent == webroot and folder.name == 'fx-admin-load-' + run and folder.exists(): shutil.rmtree(folder)
    try: fixture('drop')
    finally:
        if directory and directory.parent == pathlib.Path(tempfile.gettempdir()).resolve() and directory.name.startswith('fx-contacts-review-'):
            shutil.rmtree(directory)
