<?php
declare(strict_types=1);

// Usa exclusivamente o autoload do exemplo isolado. Nao toca em contas existentes.
$example = dirname(__DIR__) . '/examples/contacts';
require $example . '/vendor/autoload.php';
$temporary = sys_get_temp_dir() . '/fx-contacts-review-' . bin2hex(random_bytes(8));
foreach (['config', 'public', 'modules/admin/docs', 'modules/admin/resources'] as $directory) { mkdir($temporary . '/' . $directory, 0775, true); }
$package = Composer\InstalledVersions::getInstallPath('fxfavalessa/fx-admin');
copy($package . '/fx-module.json', $temporary . '/modules/admin/fx-module.json');
copy($package . '/docs/index.html', $temporary . '/modules/admin/docs/index.html');
foreach (['modules/contacts/docs', 'modules/contacts/resources', 'app/Console'] as $directory) { mkdir($temporary . '/' . $directory, 0770, true); }
foreach (['modules/contacts/fx-module.json', 'modules/contacts/docs/index.html', 'app/Console/commands.php'] as $file) { copy($example . '/' . $file, $temporary . '/' . $file); }
file_put_contents($temporary . '/config/modules.json', '{"schema":1,"manifests":["modules/admin/fx-module.json","modules/contacts/fx-module.json"]}');
file_put_contents($temporary . '/config/admin.php', '<?php return ["database" => dirname(__DIR__) . "/storage/admin.sqlite", "secure_cookie" => false, "permissions" => ["contacts.view","contacts.create","contacts.update","contacts.delete"]];');
$autoload = var_export($example . '/vendor/autoload.php', true);
file_put_contents($temporary . '/public/index.php', '<?php require ' . $autoload . '; $root=dirname(__DIR__); $app=new Fx\Framework\Foundation\Application($root); $app->instance(Fx\Framework\Admin\Panel::class, Fx\Framework\Admin\AdminConfig::panel($root)); (new Fx\Framework\Modules\ModuleManager($root))->register($app); $app->boot(); $app->make(Fx\Framework\Http\Kernel::class)->handle()->send();');
$password = bin2hex(random_bytes(16));
putenv('FX_ADMIN_TEST_PASSWORD=' . $password);
$artisan = new Fx\Framework\Console\Artisan($temporary);
$artisan->setAutoExit(false);
$output = new Symfony\Component\Console\Output\BufferedOutput();
$input = new Symfony\Component\Console\Input\ArrayInput(['command' => 'admin:init', '--email' => 'admin@example.test', '--password-env' => 'FX_ADMIN_TEST_PASSWORD']);
$input->setInteractive(false);
$checks = 0;
function adminCheck(bool $value, string $message): void { global $checks; if (!$value) { throw new RuntimeException($message); } $checks++; }
adminCheck($artisan->run($input, $output) === 0, 'admin:init falhou: ' . $output->fetch());
putenv('FX_ADMIN_TEST_PASSWORD');
adminCheck($artisan->run(new Symfony\Component\Console\Input\ArrayInput(['command'=>'contacts:init']), $output) === 0, 'contacts:init falhou.');
(new Fx\Framework\Modules\ModuleManager($temporary))->enable('contacts');
foreach (['fx-database', 'fx-view', 'fx-wordpress'] as $name) { adminCheck(!Composer\InstalledVersions::isInstalled('fxfavalessa/' . $name), 'Dependencia inesperada: ' . $name); }

$db = new PDO('sqlite:' . $temporary . '/storage/contacts.sqlite');
$db->beginTransaction(); $insert=$db->prepare('INSERT INTO contacts(name,email,phone,notes) VALUES (?,?,?,?)');
for($i=1;$i<=1000;$i++){$insert->execute(['Contato '.$i,'contact'.$i.'@example.test','','Nota sintetica']);}
$db->commit();
echo json_encode(['directory'=>$temporary,'password'=>$password,'php'=>PHP_VERSION,'sqlite'=>$db->getAttribute(PDO::ATTR_SERVER_VERSION)],JSON_THROW_ON_ERROR).PHP_EOL;
