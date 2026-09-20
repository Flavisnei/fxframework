<?php
declare(strict_types=1);
namespace Fx\Framework\Console\Installation;

use RuntimeException;

final class MinimalSetup
{
    public static function validateTarget(string $target): string
    {
        $parent = realpath(dirname($target));
        $name = basename($target);
        if ($parent === false || !is_writable($parent) || !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_-]*\z/', $name)) {
            throw new RuntimeException('Use uma pasta nova com nome simples, dentro de uma pasta existente e gravavel.');
        }
        $path = $parent . DIRECTORY_SEPARATOR . $name;
        if (file_exists($path) || is_link($path)) { throw new RuntimeException('O destino ja existe. Escolha uma pasta nova; projetos existentes nunca sao sobrescritos.'); }
        return $path;
    }

    public static function validateDatabase(array $db): void
    {
        foreach (['driver','host','port','database','username','password'] as $field) {
            if (!is_string($db[$field] ?? null) || strlen($db[$field]) > 2048 || preg_match('/[\x00-\x1f\x7f]/', $db[$field])) { throw new RuntimeException('Campo de banco invalido: ' . $field); }
        }
        if ($db['driver'] === 'sqlite') {
            if (!is_file($db['database']) || realpath($db['database']) !== $db['database']) { throw new RuntimeException('SQLite requer caminho absoluto normalizado de arquivo existente.'); }
        } elseif (!in_array($db['driver'], ['mysql','mariadb'], true)
            || !preg_match('/\A[a-zA-Z0-9.:-]+\z/', $db['host'])
            || !preg_match('/\A[a-zA-Z0-9_]+\z/', $db['database'])
            || !ctype_digit($db['port']) || (int)$db['port'] < 1 || (int)$db['port'] > 65535) {
            throw new RuntimeException('Informe MariaDB/MySQL com host, porta e nome de banco validos, ou SQLite existente.');
        }
        $extension = $db['driver'] === 'sqlite' ? 'pdo_sqlite' : 'pdo_mysql';
        if (!extension_loaded($extension)) { throw new RuntimeException('Habilite a extensao PHP ' . $extension . '.'); }
    }

    public static function testDatabase(array $db): void
    {
        self::validateDatabase($db);
        try {
            $dsn = $db['driver'] === 'sqlite' ? 'sqlite:' . $db['database'] : 'mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['database'] . ';charset=utf8mb4';
            $connection = new \PDO($dsn, $db['username'], $db['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5]);
            $connection = null;
        } catch (\Throwable) { throw new RuntimeException('Nao foi possivel conectar. Confira servidor, banco existente, usuario e senha. Nenhuma tabela foi criada.'); }
    }

    public function create(string $target, ?array $db, ?string $localCore = null, string $profile = 'minimal', array $components = [], bool $withoutAdmin = false): string
    {
        $components = SetupProfile::components($profile,$components,$withoutAdmin);
        if ($profile === 'wordpress' && $db !== null) throw new RuntimeException('WordPress usa a conexao do hospedeiro.');
        $target = self::validateTarget($target);
        if ($db !== null) { self::validateDatabase($db); }
        $repositories = [['type'=>'composer','url'=>'https://raw.githubusercontent.com/Flavisnei/fxframework/main/docs/composer']];
        $version = '1.1.0-rc.1';
        if ($localCore !== null) {
            $localCore = realpath($localCore);
            if ($localCore === false || !is_file($localCore . '/composer.json') || (json_decode(file_get_contents($localCore . '/composer.json'), true)['name'] ?? '') !== 'fxfavalessa/fx-core') { throw new RuntimeException('Informe --core-path com a pasta do pacote fx-core local.'); }
            $repositories = [['type'=>'path','url'=>str_replace('\\','/',$localCore),'options'=>['symlink'=>false,'versions'=>['fxfavalessa/fx-core'=>'dev-main']]]];
            $version = 'dev-main';
        }
        $requirements = ['php'=>'^8.1','fxfavalessa/fx-core'=>$version];
        if ($db !== null) { $requirements += ['ext-pdo'=>'*',($db['driver'] === 'sqlite' ? 'ext-pdo_sqlite' : 'ext-pdo_mysql')=>'*','symfony/dotenv'=>'^6.4 || ^7.4']; }
        $manifest = ['name'=>'app/'.strtolower(basename($target)),'type'=>'project','license'=>'proprietary','repositories'=>$repositories,'require'=>$requirements,'autoload'=>['psr-4'=>['App\\'=>'app/']],'minimum-stability'=>$localCore === null ? 'RC' : 'dev','prefer-stable'=>true,'config'=>['allow-plugins'=>false]];
        $resources = dirname(__DIR__, 3) . '/resources/setup';
        $databaseHelp = $db === null ? 'Você escolheu SEM BANCO. Nenhum componente de banco, .env ou classe de conexão foi gerado. Para adicionar depois, habilite PDO e o driver PHP apropriado, crie sua classe de conexão e configure as credenciais fora da pasta pública. Não é necessário instalar ORM. O assistente não modifica projetos existentes; não o execute sobre esta pasta.' : file_get_contents($resources . '/database.txt');
        $files = [
            'composer.json'=>json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n",
            '.gitignore'=>"/vendor/\n/.env\n/.env.*\n!/.env.example\n/storage/\n",
            'app/Saudacao.php'=>"<?php\ndeclare(strict_types=1);\nnamespace App;\nfinal class Saudacao { public function mensagem(string \$nome): string { return 'Olá, ' . \$nome . '!'; } }\n",
            'example.php'=>"<?php\nrequire __DIR__ . '/vendor/autoload.php';\necho (new App\\Saudacao())->mensagem('FX') . PHP_EOL;\n",
            'LEIA-ME.txt'=>str_replace('{{DATABASE}}', $databaseHelp, file_get_contents($resources . '/LEIA-ME.txt')),
            'docs/index.html'=>file_get_contents(dirname(__DIR__,3) . '/docs/index.html'),
        ];
        if ($db !== null) {
            $files['app/Connection.php'] = file_get_contents($resources . '/Connection.php');
            $files['.env'] = $this->environment($db);
            $files['.env.example'] = $this->environment(array_replace($db,['password'=>'','username'=>'','database'=>$db['driver']==='sqlite'?'/caminho/banco.sqlite':'meu_banco']));
        }
        $files = SetupProfile::files($files,$profile,$components,$db,$localCore);
        if (!mkdir($target,0700)) { throw new RuntimeException('Nao foi possivel criar a pasta do projeto.'); }
        foreach ($files as $name=>$content) {
            $path = $target . '/' . $name;
            if (!is_dir(dirname($path)) && !mkdir(dirname($path),0750,true)) { throw new RuntimeException('Falha ao criar estrutura; preserve a pasta para diagnostico.'); }
            $handle = fopen($path,'x');
            if ($handle === false) { throw new RuntimeException('Falha ao criar arquivo; nenhum arquivo existente foi substituido.'); }
            try { if ($name === '.env') chmod($path,0600); if (fwrite($handle,$content) !== strlen($content)) throw new RuntimeException('Falha ao gravar o projeto.'); }
            finally { fclose($handle); }
        }
        return $target;
    }

    private function environment(array $db): string
    {
        $text = '';
        foreach ($db as $key=>$value) {
            $escaped = str_replace(['\\','"','$'],['\\\\','\\"','\\$'],$value);
            $text .= 'DB_' . strtoupper($key) . '="' . $escaped . '"' . "\n";
        }
        return $text;
    }
}
