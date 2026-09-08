<?php
declare(strict_types=1);
// Cria e remove somente um banco aleatório fx_review_*; nunca usa banco da aplicação.
require dirname(__DIR__,2).'/examples/database/vendor/autoload.php';
$port=getenv('FX_TEST_MYSQL_PORT');
if ($port===false || !ctype_digit($port) || (int)$port<1 || (int)$port>65535) { fwrite(STDERR,"Defina FX_TEST_MYSQL_PORT para um servidor MySQL/MariaDB de testes em 127.0.0.1.\n");exit(1); }
$user=getenv('FX_TEST_MYSQL_USER') ?: 'root';$password=getenv('FX_TEST_MYSQL_PASSWORD') ?: '';
$name='fx_review_'.bin2hex(random_bytes(8));$path=sys_get_temp_dir().'/'.$name;mkdir($path,0700);
$pdo=null;$connection=null;$capsule=null;$created=false;$checks=0;
function dbCheck(bool $ok,string $message): void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
try {
 $pdo=new PDO('mysql:host=127.0.0.1;port='.$port.';charset=utf8mb4',$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
 $capsule=Fx\Framework\Database\Database::boot(['driver'=>'mysql','host'=>'127.0.0.1','port'=>(int)$port,'database'=>$name,'username'=>$user,'password'=>$password,'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'']);
 $connection=$capsule->getConnection();
 file_put_contents($path.'/001_items.php', '<?php return new class extends Fx\\Framework\\Database\\Migration { public function up(Illuminate\\Database\\Schema\\Builder $s): void {$s->create("items",function($t){$t->increments("id");$t->string("name");$t->string("email",191)->unique();});} public function down(Illuminate\\Database\\Schema\\Builder $s): void {$s->drop("items");}};');
 $migrator=new Fx\Framework\Database\Migrator($connection,$path);
 dbCheck($migrator->migrate()===['001_items'],'Migration falhou.');
 dbCheck($migrator->migrate()===[],'Migration repetida executou novamente.');
 $id=$connection->table('items')->insertGetId(['name'=>'Ação 🌶','email'=>'one@example.test']);
 dbCheck($connection->table('items')->where('id',$id)->value('name')==='Ação 🌶','UTF-8 incorreto.');
 $connection->beginTransaction();$connection->table('items')->insert(['name'=>'Reverter','email'=>'rollback@example.test']);$connection->rollBack();
 dbCheck($connection->table('items')->count()===1,'Rollback DML falhou.');
 $duplicate=false;try{$connection->table('items')->insert(['name'=>'Duplicado','email'=>'one@example.test']);}catch(Illuminate\Database\QueryException){$duplicate=true;}
 dbCheck($duplicate,'Indice unico falhou.');
 dbCheck($connection->table('items')->where('id',$id)->update(['name'=>'Editado'])===1,'Edicao falhou.');
 dbCheck($connection->table('items')->where('id',$id)->delete()===1,'Exclusao falhou.');
 dbCheck($migrator->rollback()===['001_items'],'Rollback da migration falhou.');
 dbCheck(!$connection->getSchemaBuilder()->hasTable('items'),'Tabela permaneceu.');
 dbCheck($migrator->rollback()===[],'Rollback vazio falhou.');
 echo json_encode(['checks'=>$checks,'php'=>PHP_VERSION,'server'=>$pdo->getAttribute(PDO::ATTR_SERVER_VERSION),'driver'=>'mysql','admin_contacts_ported'=>false],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
 if($capsule){$capsule->getDatabaseManager()->purge();}$connection=null;$capsule=null;
 if($created){$pdo->exec('DROP DATABASE `'.$name.'`');}
 foreach(glob($path.'/*') as $file){unlink($file);}rmdir($path);
}
