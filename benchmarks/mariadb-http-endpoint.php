<?php
declare(strict_types=1);
// Template de teste: copiado em pasta aleatória no Apache com configuração fora do webroot.
if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){http_response_code(403);exit;}
$config=require __DIR__.'/config-path.php';
if(!hash_equals($config['token'],$_SERVER['HTTP_X_FX_TEST_TOKEN']??'')){http_response_code(403);exit;}
require $config['autoload'];
$app=new Fx\Framework\Foundation\Application(__DIR__);
$db=Fx\Framework\Database\Database::boot(['driver'=>'mysql','host'=>'127.0.0.1','port'=>$config['port'],'database'=>$config['database'],'username'=>$config['user'],'password'=>$config['password'],'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>''])->getConnection();
$route=$app->make(Fx\Framework\Routing\Router::class);
$route->get('/',static function()use($db){
 return ['total'=>$db->table('items')->count(),'data'=>$db->table('items')->orderBy('id')->limit(20)->get()->all(),'writes'=>$db->table('writes_log')->count(),'counter'=>(int)$db->table('counter')->where('id',1)->value('value'),'php'=>PHP_VERSION,'sapi'=>PHP_SAPI];
});
$route->post('/',static function(Fx\Framework\Http\Request $request)use($db){
 $marker=$request->input('marker');
 if(!is_string($marker)||!preg_match('/\A[a-f0-9]{24}-[0-9]+-[0-9]+\z/',$marker)){throw new Symfony\Component\HttpKernel\Exception\HttpException(422,'Marcador invalido.');}
 $db->transaction(static function()use($db,$marker){$db->table('writes_log')->insert(['marker'=>$marker]);$db->table('counter')->where('id',1)->increment('value');});
 return ['saved'=>true];
});
$app->boot();$app->make(Fx\Framework\Http\Kernel::class)->handle()->send();
