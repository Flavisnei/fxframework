"""Instala dev-main, atualiza para RC e restaura lock, verificando conta MariaDB."""
import argparse, json, os, pathlib, secrets, shutil, subprocess, tempfile

root=pathlib.Path(__file__).resolve().parents[2]
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--php',default='php')
parser.add_argument('--composer',required=True)
parser.add_argument('--repository',required=True,help='URL HTTPS ou file:// do diretório com packages.json')
parser.add_argument('--version',required=True)
args=parser.parse_args()
directory=pathlib.Path(tempfile.mkdtemp(prefix='fx-release-test-')).resolve()
database={'database':'fx_wp_matrix_'+secrets.token_hex(12),'port':int(os.getenv('FX_TEST_MYSQL_PORT','3306')),
          'user':os.getenv('FX_TEST_MYSQL_USER','root'),'password':os.getenv('FX_TEST_MYSQL_PASSWORD','')}

def db(action):
    result=subprocess.run([args.php,str(root/'tests/integration/wordpress-database.php'),action],input=json.dumps(database),text=True,capture_output=True)
    if result.returncode: raise RuntimeError('Banco temporário da release: '+action+' falhou.')

def composer(command):
    result=subprocess.run([args.php,str(pathlib.Path(args.composer).resolve()),command,'--no-interaction','--prefer-dist','--no-progress','--no-plugins','--no-scripts'],cwd=directory,capture_output=True,text=True,encoding='utf-8',errors='replace')
    if result.returncode: raise RuntimeError(result.stderr)

def probe(action):
    result=subprocess.run([args.php,str(root/'tests/integration/release-probe.php'),action],input=json.dumps(config),text=True,capture_output=True)
    if result.returncode: raise RuntimeError('Verificação do consumidor falhou; configuração omitida.')
    return json.loads(result.stdout)

def refs():
    return {p['name']:{'version':p['version'],'reference':p.get('dist',{}).get('reference')} for p in json.loads((directory/'composer.lock').read_text())['packages'] if p['name'].startswith('fxfavalessa/')}

try:
    db('create')
    config={'consumer':str(directory),'password':secrets.token_hex(24),'database':{'driver':'mysql','host':'127.0.0.1','port':database['port'],
        'name':database['database'],'username':database['user'],'password':database['password']}}
    manifest={'name':'fx-test/release','minimum-stability':'dev','prefer-stable':True,
        'repositories':[{'type':'composer','url':args.repository}],
        'require':{'fxfavalessa/fx-'+name:'dev-main' for name in ['admin','auth','console','core','http','modules','validation','windows']}}
    (directory/'composer.json').write_text(json.dumps(manifest),encoding='utf-8')
    composer('update'); baseline=probe('create'); original=refs()
    if any(p['version']!='dev-main' for p in original.values()): raise RuntimeError('Baseline deve usar somente snapshots anteriores.')
    old_manifest=(directory/'composer.json').read_bytes(); old_lock=(directory/'composer.lock').read_bytes()
    manifest['minimum-stability']='RC'
    manifest['require']={name:args.version for name in manifest['require']}
    (directory/'composer.json').write_text(json.dumps(manifest),encoding='utf-8')
    composer('update'); upgraded=probe('verify'); candidate=refs()
    if not candidate or any(p['version']!=args.version for p in candidate.values()): raise RuntimeError('Versões FX não ficaram fixadas no candidato.')
    (directory/'composer.json').write_bytes(old_manifest); (directory/'composer.lock').write_bytes(old_lock)
    composer('install'); restored=probe('verify')
    if refs()!=original: raise RuntimeError('Retorno não restaurou referências originais.')
    print(json.dumps({'schema':1,'version':args.version,'baseline':baseline,'upgraded':upgraded,'restored':restored,
        'original_packages':original,'candidate_packages':candidate,'lock_restored':True,
        'scope':'Consumidor isolado Admin+Console; ZIPs remotos; conta MariaDB preservada; sem migration de schema.'},ensure_ascii=False,indent=2))
finally:
    try: db('drop')
    finally:
        if directory.parent==pathlib.Path(tempfile.gettempdir()).resolve() and directory.name.startswith('fx-release-test-'): shutil.rmtree(directory)
