"""Carga HTTP local com clientes simultâneos e dados descartáveis; só biblioteca padrão."""
import argparse, concurrent.futures, http.cookiejar, json, math, os, pathlib, platform
import shutil, socket, subprocess, sys, threading, time, urllib.request

root = pathlib.Path(__file__).resolve().parent.parent
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--php', default='php')
parser.add_argument('--clients', type=int, default=4)
parser.add_argument('--requests', type=int, default=50, help='Requisições medidas por cliente')
args = parser.parse_args()
if not 1 <= args.clients <= 16 or not 5 <= args.requests <= 500:
    parser.error('Use 1–16 clientes e 5–500 requisições por cliente.')
fixture = json.loads(subprocess.check_output([args.php, str(root/'benchmarks/http-fixture.php')], cwd=root))
directory = pathlib.Path(fixture['directory']).resolve()
server = None
try:
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]
    url = f'http://127.0.0.1:{port}'
    with open(directory/'http-load.log', 'wb') as log:
        env = os.environ.copy(); env.pop('PHP_CLI_SERVER_WORKERS', None)
        server = subprocess.Popen([args.php, '-S', f'127.0.0.1:{port}', '-t', str(directory/'public'), str(directory/'public/index.php')], cwd=directory, stdout=log, stderr=log, env=env)
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.1): break
            except OSError: time.sleep(.05)
        else: raise RuntimeError('Servidor local não iniciou.')
        started = [None]
        barrier = threading.Barrier(args.clients, action=lambda: started.__setitem__(0, time.perf_counter()), timeout=30)
        def client(index):
            opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
            def request(path, data=None, csrf=''):
                req=urllib.request.Request(url+path, data=None if data is None else json.dumps(data).encode(), headers={'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf})
                with opener.open(req, timeout=10) as response:
                    raw=response.read(); return json.loads(raw), len(raw)
            try:
                session,_=request('/admin/api/session')
                request('/admin/api/login', {'email':'admin@example.test','password':fixture['password']}, session['csrf'])
                for _ in range(2):
                    page,_=request('/admin/api/contacts?page=1')
                    if page.get('total')!=1000 or len(page.get('data',[]))!=20: raise RuntimeError('Aquecimento inválido.')
            except Exception:
                barrier.abort(); raise
            barrier.wait()
            latencies=[]; sizes=[]; errors=0
            for _ in range(args.requests):
                start=time.perf_counter()
                try:
                    page,size=request('/admin/api/contacts?page=1')
                    if page.get('total')!=1000 or len(page.get('data',[]))!=20: raise RuntimeError('Resposta inválida.')
                    latencies.append((time.perf_counter()-start)*1000); sizes.append(size)
                except Exception: errors+=1
            return latencies,sizes,errors
        with concurrent.futures.ThreadPoolExecutor(max_workers=args.clients) as pool:
            results=list(pool.map(client, range(args.clients)))
        elapsed=time.perf_counter()-started[0]
        timings=sorted(t for row in results for t in row[0]); sizes=[s for row in results for s in row[1]]; errors=sum(row[2] for row in results)
        def percentile(p): return timings[math.ceil(p*len(timings))-1] if timings else None
        report={'schema':1,'php':fixture['php'],'sqlite':fixture['sqlite'],'os':platform.platform(),'python':platform.python_version(),'server':'PHP built-in, single process','clients':args.clients,'requests_per_client':args.requests,'warmups_per_client':2,'rows':1000,'page_size':20,'successful_requests':len(timings),'errors':errors,'elapsed_seconds':elapsed,'successful_requests_per_second':len(timings)/elapsed,'p50_ms':percentile(.5),'p95_ms':percentile(.95),'body_bytes_min':min(sizes) if sizes else None,'body_bytes_max':max(sizes) if sizes else None,'scope':'Loopback authenticated GET only; separate sessions; single-process development server; not production capacity or write workload'}
        print(json.dumps(report,ensure_ascii=False,indent=2))
        if errors: sys.exit(1)
finally:
    if server:
        server.terminate()
        try: server.wait(timeout=10)
        except subprocess.TimeoutExpired: server.kill(); server.wait()
    # Fixture gerou esse diretório; recusa remover qualquer outro caminho.
    if directory.name.startswith('fx-contacts-review-') and directory.parent == pathlib.Path(__import__('tempfile').gettempdir()).resolve():
        shutil.rmtree(directory)
