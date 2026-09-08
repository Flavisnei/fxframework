<?php
declare(strict_types=1);
// Somente CLI: banco sintético, nenhuma enumeração ou alteração de bancos do usuário.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/vendor/autoload.php';
$action=$argv[1]??'';$config=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
$name=$config['database']??'';
if(!preg_match('/\Afx_load_[a-f0-9]{24}\z/',$name)){throw new RuntimeException('Nome temporario invalido.');}
$pdo=new PDO('mysql:host=127.0.0.1;port='.(int)$config['port'].';charset=utf8mb4',$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if($action==='drop'){$pdo->exec('DROP DATABASE IF EXISTS `'.$name.'`');echo '{"removed":true}';exit;}
if($action!=='create'){throw new RuntimeException('Acao invalida.');}
$pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `'.$name.'`');
$pdo->exec('CREATE TABLE items(id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(120) NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE writes_log(id INT PRIMARY KEY AUTO_INCREMENT, marker VARCHAR(80) NOT NULL UNIQUE) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE counter(id INT PRIMARY KEY, value INT NOT NULL) ENGINE=InnoDB');
$pdo->exec('INSERT INTO counter VALUES(1,0)');$pdo->beginTransaction();$q=$pdo->prepare('INSERT INTO items(name) VALUES(?)');for($i=1;$i<=1000;$i++){$q->execute(['Ação '.$i]);}$pdo->commit();
$admin=false;$contacts=false;
try{new Fx\Framework\Admin\AdminStore($pdo);$admin=true;}catch(InvalidArgumentException){}
require dirname(__DIR__).'/examples/contacts/modules/contacts/src/ContactStore.php';
try{new Example\Contacts\ContactStore($pdo);$contacts=true;}catch(PDOException){}
echo json_encode(['server'=>$pdo->getAttribute(PDO::ATTR_SERVER_VERSION),'admin_accepts_mariadb'=>$admin,'contacts_accepts_mariadb'=>$contacts],JSON_THROW_ON_ERROR);
