<?php
declare(strict_types=1);
namespace Fx\Framework\Console\Installation;

final class SetupProfile
{
    public const LABELS = ['minimal'=>'Minima','complete'=>'Completa','custom'=>'Personalizada','wordpress'=>'WordPress'];
    public static function components(string $profile, array $components = [], bool $withoutAdmin = false): array
    {
        if (!isset(self::LABELS[$profile])) throw new \RuntimeException('Perfil invalido: minimal, complete, custom ou wordpress.');
        if ($profile !== 'custom' && $components !== []) throw new \RuntimeException('--components so pode ser usado no perfil custom.');
        if ($withoutAdmin && $profile !== 'complete') throw new \RuntimeException('--without-admin requer perfil complete.');
        $chosen = match($profile) { 'minimal'=>[], 'complete'=>array_values(array_diff(array_keys(PackageCatalog::COMPONENTS), $withoutAdmin ? ['wordpress','admin'] : ['wordpress'])), 'wordpress'=>['wordpress'], default=>$components };
        foreach ($chosen as $name) {
            PackageCatalog::package($name);
            if (!isset(PackageCatalog::COMPONENTS[$name]) || ($profile === 'custom' && $name === 'wordpress')) throw new \RuntimeException('Use nomes curtos de componentes; para WordPress escolha o perfil wordpress.');
        }
        if (in_array('admin',$chosen,true)) $chosen[]='console';
        return array_values(array_unique(['core',...$chosen]));
    }

    /** Ajusta somente arquivos ainda em memoria, antes de criar o destino. */
    public static function files(array $files, string $profile, array $components, ?array $db, ?string $localCore): array
    {
        if ($profile === 'minimal') return $files;
        $manifest=json_decode($files['composer.json'],true,512,JSON_THROW_ON_ERROR);
        $version=$localCore===null?PackageCatalog::PUBLISHED_VERSION:'dev-main';
        foreach($components as $name)$manifest['require'][PackageCatalog::package($name)]=$version;
        if ($localCore!==null) {
            $parent=dirname(realpath($localCore));$versions=[];
            foreach(array_keys(PackageCatalog::COMPONENTS) as $name) {
                $file=$parent.'/'.$name.'/composer.json';
                if(!is_file($file))throw new \RuntimeException('Para perfis adicionais, --core-path deve pertencer ao checkout completo packages/core.');
                $data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
                if(($data['name']??'')!==PackageCatalog::package($name))throw new \RuntimeException('Pacote local invalido.');
                $versions[$data['name']]='dev-main';
            }
            $manifest['repositories']=[['type'=>'path','url'=>str_replace('\\','/',$parent).'/*','options'=>['symlink'=>false,'versions'=>$versions]]];
        }
        $resources=dirname(__DIR__,3).'/resources/setup';
        $instructions='';
        if(in_array('console',$components,true)) {
            $files['fxartisan']=file_get_contents($resources.'/fxartisan');
            $files['LEIA-ME.txt'].="\nFX ARTISAN — TERMINAL\nNa raiz: php fxartisan (lista comandos), php fxartisan help make:controller (ajuda), php fxartisan make:controller Exemplo (gera uma classe).\nRequer PHP 8.1 ou superior e composer install concluido. O atalho sempre trabalha na pasta onde esta salvo.\nOs comandos dependem dos componentes instalados. php vendor/bin/fxartisan continua disponivel, executado na raiz.\nSe aparecer Could not open input file, entre na pasta do projeto ou informe o caminho completo do arquivo fxartisan.\nNao coloque este arquivo em public. Consulte docs/index.html para instalacao e exemplos.\n";
        }
        if(in_array('admin',$components,true)) {
            $manifest['require']['symfony/dotenv']='^6.4 || ^7.4';
            $manifest['require']['symfony/mailer']='^6.4';
            foreach(['bootstrap.php','public/index.php','config/admin.php','configure.php'] as $file)$files[$file]=file_get_contents($resources.'/admin/'.$file);
            $files['config/modules.json']=json_encode(['schema'=>1,'manifests'=>['vendor/fxfavalessa/fx-admin/fx-module.json']],JSON_THROW_ON_ERROR);
            $files['storage/.gitkeep']='';
            if($db===null) {
                $files['.env']="APP_SECURE_COOKIE=0\nFX_MAIL_ENABLED=0\nAPP_URL=\"\"\n";
                $files['.env.example']=$files['.env'];
            } else {
                $files['.env'].="APP_SECURE_COOKIE=0\nFX_MAIL_ENABLED=0\nAPP_URL=\"\"\n";
                $files['.env.example'].="APP_SECURE_COOKIE=0\nFX_MAIL_ENABLED=0\nAPP_URL=\"\"\n";
            }
            $instructions="PAINEL ADMINISTRATIVO\nExecute php configure.php para criar o primeiro administrador com senha oculta.\nEsse passo cria as tabelas administrativas, preservando contas existentes.\nDepois: php -S 127.0.0.1:8080 -t public public/index.php\nAbra http://127.0.0.1:8080/admin. No XAMPP, acesse /nome-do-projeto/public/; sem rewrite, /nome-do-projeto/public/index.php/admin. O configure.php habilita o modulo fx-admin.\nSem banco externo, usa storage/admin.sqlite criado ao configurar.\nAmbiente local HTTP: APP_SECURE_COOKIE=0. Em producao HTTPS, altere para 1.\nConfigure SMTP depois no painel se a versao instalada oferecer essa tela.\nO SMTP fica desativado inicialmente. Nao ha contatos ou outros modulos de negocio.\n";
        } elseif(in_array('http',$components,true)) {
            $files['public/index.php']=file_get_contents($resources.'/http-index.php');
            $instructions="HTTP\nExecute php -S 127.0.0.1:8080 -t public public/index.php e abra / para ver a pagina inicial; /api/status retorna JSON.\nAcrescente manualmente rotas, autenticacao, validacao e demais componentes.\n";
        }
        if($profile==='wordpress') {
            $files['plugin.php']=file_get_contents($resources.'/wordpress-plugin.php');
            $files['src/Plugin.php'] = <<<'PHP'
<?php
declare(strict_types=1);

namespace App;

use Fx\Framework\WordPress\Plugin as FxPlugin;

final class Plugin
{
    public function __construct(private readonly string $file) {}

    public function boot(): void
    {
        $plugin = new FxPlugin($this->file, 'fx-projeto');
        $plugin->app()->boot();
        $plugin->action('admin_menu', static function (): void {
            add_management_page('FX Projeto', 'FX Projeto', 'manage_options', 'fx-projeto', static function (): void {
                require __DIR__ . '/../templates/admin-page.php';
            });
        });
    }
}
PHP;
            $files['templates/admin-page.php'] = <<<'PHP'
<div class="wrap">
    <h1>FX Projeto</h1>
    <p>Plugin criado pelo FX Framework. Personalize esta página e acrescente seus módulos.</p>
</div>
PHP;
            $files['assets/js/admin.js'] = "'use strict';\n\n// Adicione aqui os comportamentos AJAX do seu plugin.\n";
            $files['assets/css/admin.css'] = "/* Estilos administrativos do plugin. */\n";
            $files['assets/img/.gitkeep'] = '';
            $files['README.md'] = "# FX Projeto\n\nPlugin WordPress gerado pelo FX Framework.\n\n- `plugin.php`: bootstrap do plugin.\n- `src/`: código PHP da aplicação.\n- `templates/`: HTML/PHP das telas.\n- `assets/js`, `assets/css`, `assets/img`: recursos públicos.\n";
            $instructions="WORDPRESS\nInstale as dependencias e copie esta pasta inteira, incluindo vendor, para wp-content/plugins.\nAtive FX Projeto no WordPress. Usa usuarios, permissoes e banco do WordPress; nao cria login paralelo.\nO menu Ferramentas > FX Projeto demonstra a integracao. Personalize Plugin Name e Text Domain.\nNao execute plugin.php diretamente. Nao instala nem altera o WordPress hospedeiro.\n";
        }
        if(isset($files['public/index.php'])) {
            $files['public/.htaccess']=file_get_contents($resources.'/public.htaccess');
            $files['.htaccess']=file_get_contents($resources.'/root.htaccess');
        }
        $files=array_replace($files,ProjectStructure::files($components));
        if(in_array('database',$components,true))$manifest['require']['symfony/dotenv']='^6.4 || ^7.4';
        $list=implode(', ',$components);
        $files['composer.json']=json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        $files['LEIA-ME.txt']=str_replace("FX — INSTALAÇÃO MÍNIMA\n======================\nEste projeto contém a base FX Core. Não inclui painel, login, ORM ou migrations.","FX — PERFIL ".self::LABELS[$profile]."\nComponentes diretos: ".$list.". Dependencias adicionais resolvidas pelo Composer.\n".$instructions,$files['LEIA-ME.txt']);
        if(in_array('admin',$components,true) && $db===null)$files['LEIA-ME.txt']=str_replace('Você escolheu SEM BANCO. Nenhum componente de banco, .env ou classe de conexão foi gerado. Para adicionar depois, habilite PDO e o driver PHP apropriado, crie sua classe de conexão e configure as credenciais fora da pasta pública. Não é necessário instalar ORM. O assistente não modifica projetos existentes; não o execute sobre esta pasta.','O painel usa SQLite local por padrao. Configure o administrador para criar o arquivo e as tabelas. ORM depende dos componentes selecionados.',$files['LEIA-ME.txt']);
        if($profile==='wordpress')$files['LEIA-ME.txt']=str_replace('Você escolheu SEM BANCO. Nenhum componente de banco, .env ou classe de conexão foi gerado. Para adicionar depois, habilite PDO e o driver PHP apropriado, crie sua classe de conexão e configure as credenciais fora da pasta pública. Não é necessário instalar ORM. O assistente não modifica projetos existentes; não o execute sobre esta pasta.','Use o banco configurado pelo WordPress e suas APIs. Nenhuma credencial separada foi gerada.',$files['LEIA-ME.txt']);
        if(in_array('database',$components,true))$files['LEIA-ME.txt'].="\nCOMPONENTE DATABASE\nVoce selecionou explicitamente Eloquent e migrations. A conexao PDO opcional permanece independente: configure .env e revise migrations conforme vendor/fxfavalessa/fx-database/docs/index.html.\n";
        if(in_array('database',$components,true)) {
            $files['LEIA-ME.txt']=str_replace('Foi configurada somente a conexão PDO. Symfony Dotenv carrega .env; não há ORM.','A conexao PDO abaixo e independente do ORM instalado por sua escolha.',$files['LEIA-ME.txt']);
            $files['LEIA-ME.txt']=str_replace('Você escolheu SEM BANCO. Nenhum componente de banco, .env ou classe de conexão foi gerado.','Nenhuma conexao automatica foi configurada. O pacote database foi instalado por sua escolha; configure sua conexao manualmente.',$files['LEIA-ME.txt']);
        }
        $files['LEIA-ME.txt']=str_replace('O perfil mínimo não configura rotas HTTP, autenticação nem proteção CSRF.','Recursos do perfil instalado estao descritos no inicio deste guia. Para endpoints novos,',$files['LEIA-ME.txt']);
        $files['LEIA-ME.txt'].="\nAJUDA DOS COMPONENTES\nCada pacote instalado inclui seu manual em vendor/fxfavalessa/fx-NOME/docs/index.html.\nLeia LEIA-ME-ESTRUTURA.txt para pastas, rotas, views e evolucao. Integracoes de negocio permanecem manuais.\n";
        return $files;
    }
}
