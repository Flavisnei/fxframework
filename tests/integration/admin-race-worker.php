<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/vendor/autoload.php';
$data=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
$db=Fx\Framework\Admin\AdminConfig::connection(['database'=>$data['database']]);
$store=new Fx\Framework\Admin\AdminStore($db);
file_put_contents($data['directory'].'/ready-'.$data['id'],'ready');
$deadline=microtime(true)+20;
while(!is_file($data['directory'].'/start')) {if(microtime(true)>$deadline)exit(2);usleep(10000);clearstatcache();}
try {
 if($data['action']==='demote') {$store->saveUser($data['id'],['name'=>'Admin '.$data['id'],'email'=>'a'.$data['id'].'@example.test','role_id'=>2,'active'=>true,'password'=>'']);}
 elseif($data['action']==='reset') {$store->resetPassword($data['token'],'changed secret 123');}
 else {$store->throttle('same-key',1);}
 echo '200';
} catch(Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {echo $error->getStatusCode();}
