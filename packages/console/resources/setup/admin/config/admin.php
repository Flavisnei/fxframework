<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$values=(new Symfony\Component\Dotenv\Dotenv())->parse(file_get_contents($root.'/.env'));
$read=static fn(string $key,string $default=''):string => getenv($key)!==false?getenv($key):($values[$key]??$default);
$driver=$read('DB_DRIVER','sqlite');
if(!in_array($driver,['sqlite','mysql','mariadb'],true))throw new RuntimeException('DB_DRIVER invalido.');
$database=$driver==='sqlite' ? $read('DB_DATABASE',$root.'/storage/admin.sqlite') : ['driver'=>'mysql','host'=>$read('DB_HOST','127.0.0.1'),'port'=>(int)$read('DB_PORT','3306'),'name'=>$read('DB_DATABASE'),'username'=>$read('DB_USERNAME'),'password'=>$read('DB_PASSWORD')];
return ['database'=>$database,'secure_cookie'=>$read('APP_SECURE_COOKIE','1')==='1','mail_settings'=>true];
