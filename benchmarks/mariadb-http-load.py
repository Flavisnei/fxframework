"""Teste opt-in no Apache local: GET e POST transacionais com MariaDB temporário."""
import argparse,concurrent.futures,json,math,os,pathlib,secrets,shutil,subprocess,tempfile,threading,time,urllib.request,urllib.error
root=pathlib.Path(__file__).resolve().parent.parent
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--php',default='php');parser.add_argument('--webroot',required=True)
parser.add_argument('--port',type=int,default=3306);parser.add_argument('--clients',type=int,default=4);parser.add_argument('--requests',type=int,default=50)
args=parser.parse_args()
if not 1<=args.clients<=16 or not 5<=args.requests<=500 or not 1<=args.port<=65535:parser.error('Limites: 1–16 clientes, 5–500 operações por cliente e porta válida.')
webroot=pathlib.Path(args.webroot).resolve()
if not webroot.is_dir():parser.error('Webroot deve existir e corresponder ao Apache local na porta 80.')
run=secrets.token_hex(12);folder=webroot/('fx-load-'+run)
private=pathlib.Path(tempfile.mkdtemp(prefix='fx-load-config-'))
config={'database':'fx_load_'+run,'port':args.port,'user':os.getenv('FX_TEST_MYSQL_USER','root'),'password':os.getenv('FX_TEST_MYSQL_PASSWORD',''),'token':secrets.token_hex(32),'autoload':(root/'vendor/autoload.php').as_posix()}
created=False

def fixture(action):
 p=subprocess.run([args.php,str(root/'benchmarks/mariadb-http-fixture.php'),action],input=json.dumps(config),text=True,capture_output=True,cwd=root)
 if p.returncode:raise RuntimeError('Fixture '+action+' falhou; saída omitida para não expor configuração.')
 return json.loads(p.stdout)
try:
 created=True;proof=fixture('create')
 secret=private/'config.json';secret.write_text(json.dumps(config),encoding='utf-8')
 folder.mkdir();shutil.copyfile(root/'benchmarks/mariadb-http-endpoint.php',folder/'index.php')
 # Caminho escapado como literal PHP; configuração e senha ficam fora do webroot.
 literal=secret.as_posix().replace('\\','\\\\').replace("'","\\'")
 (folder/'config-path.php').write_text("<?php return json_decode(file_get_contents('"+literal+"'),true,32,JSON_THROW_ON_ERROR);",encoding='utf-8')
 url='http://127.0.0.1/'+folder.name+'/index.php'
 def request(data=None,token=True):
  opener=urllib.request.build_opener(urllib.request.ProxyHandler({}))
  headers={'Content-Type':'application/json','Accept':'application/json'}
  if token:headers['X-FX-TEST-TOKEN']=config['token']
  req=urllib.request.Request(url,data=None if data is None else json.dumps(data).encode(),headers=headers)
  with opener.open(req,timeout=15) as response:
   raw=response.read();return json.loads(raw),len(raw)
 try:request(token=False);raise RuntimeError('Endpoint aceitou acesso sem token.')
 except urllib.error.HTTPError as error:
  if error.code!=403:raise
 page,_=request()
 if page['total']!=1000 or len(page['data'])!=20:raise RuntimeError('Preparação inválida.')
 start=[None];barrier=threading.Barrier(args.clients,action=lambda:start.__setitem__(0,time.perf_counter()),timeout=30)
 def client(index):
  timings={'read':[],'write':[]};errors=[];barrier.wait()
  for n in range(args.requests):
   kind='write' if n%2 else 'read';t=time.perf_counter()
   try:
    data,_=request({'marker':f'{run}-{index}-{n}'} if kind=='write' else None)
    if kind=='write' and data.get('saved') is not True:raise RuntimeError('Gravação inválida.')
    if kind=='read' and (data.get('total')!=1000 or len(data.get('data',[]))!=20):raise RuntimeError('Leitura inválida.')
    timings[kind].append((time.perf_counter()-t)*1000)
   except Exception as e:errors.append(type(e).__name__)
  return timings,errors
 with concurrent.futures.ThreadPoolExecutor(max_workers=args.clients) as pool:results=list(pool.map(client,range(args.clients)))
 elapsed=time.perf_counter()-start[0];final,_=request();expected=args.clients*(args.requests//2)
 errors=[e for row in results for e in row[1]]
 metrics={}
 for kind in ['read','write']:
  values=sorted(x for row in results for x in row[0][kind]);metrics[kind]={'successes':len(values),'p50_ms':values[math.ceil(.5*len(values))-1] if values else None,'p95_ms':values[math.ceil(.95*len(values))-1] if values else None}
 integrity=final['writes']==expected and final['counter']==expected
 report={'schema':1,'database':proof,'php':page['php'],'sapi':page['sapi'],'clients':args.clients,'requests_per_client':args.requests,'metrics':metrics,'errors':len(errors),'error_types':sorted(set(errors)),'elapsed_seconds':elapsed,'successful_requests_per_second':sum(m['successes'] for m in metrics.values())/elapsed,'expected_writes':expected,'stored_writes':final['writes'],'counter':final['counter'],'integrity':integrity,'scope':'Apache local + FX HTTP/Database + MariaDB; synthetic token; no Admin session; not production capacity'}
 print(json.dumps(report,ensure_ascii=False,indent=2))
 if errors or not integrity:raise SystemExit(1)
finally:
 # Só as pastas aleatórias desta execução; nunca remove o webroot.
 if folder.parent==webroot and folder.name=='fx-load-'+run and folder.exists():shutil.rmtree(folder)
 try:
  if created:fixture('drop')
 finally:shutil.rmtree(private)
