<?php
declare(strict_types=1);
namespace Fx\Framework\Console\Installation;

final class SetupProfile
{
    public const LABELS = ['minimal'=>'Minima','complete'=>'Completa','custom'=>'Personalizada','wordpress'=>'WordPress'];
    public static function components(string $profile, array $components = []): array
    {
        if (!isset(self::LABELS[$profile])) throw new \RuntimeException('Perfil invalido: minimal, complete, custom ou wordpress.');
        if ($profile !== 'custom' && $components !== []) throw new \RuntimeException('--components so pode ser usado no perfil custom.');
        $chosen = match($profile) { 'minimal'=>[], 'complete'=>['admin','console'], 'wordpress'=>['wordpress'], default=>$components };
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
        $version=$localCore===null?'1.1.0-rc.1':'dev-main';
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
            $instructions="HTTP\nExecute php -S 127.0.0.1:8080 -t public public/index.php e abra / para receber JSON.\nAcrescente manualmente rotas, autenticacao, validacao e demais componentes.\n";
        }
        if($profile==='wordpress') {
            $files['plugin.php']=file_get_contents($resources.'/wordpress-plugin.php');
            $instructions="WORDPRESS\nInstale as dependencias e copie esta pasta inteira, incluindo vendor, para wp-content/plugins.\nAtive FX Projeto no WordPress. Usa usuarios, permissoes e banco do WordPress; nao cria login paralelo.\nO menu Ferramentas > FX Projeto demonstra a integracao. Personalize Plugin Name e Text Domain.\nNao execute plugin.php diretamente. Nao instala nem altera o WordPress hospedeiro.\n";
        }
        if(isset($files['public/index.php'])) {
            $files['public/.htaccess']=file_get_contents($resources.'/public.htaccess');
            $files['.htaccess']=file_get_contents($resources.'/root.htaccess');
        }
        $list=implode(', ',$components);
        $files['composer.json']=json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        $files['LEIA-ME.txt']=str_replace("FX — INSTALAÇÃO MÍNIMA\n======================\nEste projeto contém a base FX Core. Não inclui painel, login, ORM ou migrations.","FX — PERFIL ".self::LABELS[$profile]."\nComponentes diretos: ".$list.". Dependencias adicionais resolvidas pelo Composer.\n".$instructions,$files['LEIA-ME.txt']);
        if(in_array('admin',$components,true) && $db===null)$files['LEIA-ME.txt']=str_replace('Você escolheu SEM BANCO. Nenhum componente de banco, .env ou classe de conexão foi gerado. Para adicionar depois, habilite PDO e o driver PHP apropriado, crie sua classe de conexão e configure as credenciais fora da pasta pública. Não é necessário instalar ORM. O assistente não modifica projetos existentes; não o execute sobre esta pasta.','O painel usa SQLite local por padrao. Configure o administrador para criar o arquivo e as tabelas. O perfil administrativo nao instala ORM.',$files['LEIA-ME.txt']);
        if($profile==='wordpress')$files['LEIA-ME.txt']=str_replace('Você escolheu SEM BANCO. Nenhum componente de banco, .env ou classe de conexão foi gerado. Para adicionar depois, habilite PDO e o driver PHP apropriado, crie sua classe de conexão e configure as credenciais fora da pasta pública. Não é necessário instalar ORM. O assistente não modifica projetos existentes; não o execute sobre esta pasta.','Use o banco configurado pelo WordPress e suas APIs. Nenhuma credencial separada foi gerada.',$files['LEIA-ME.txt']);
        if(in_array('database',$components,true))$files['LEIA-ME.txt'].="\nCOMPONENTE DATABASE\nVoce selecionou explicitamente Eloquent e migrations. A conexao PDO opcional permanece independente: configure providers e migrations manualmente conforme vendor/fxfavalessa/fx-database/docs/index.html.\n";
        if(in_array('database',$components,true)) {
            $files['LEIA-ME.txt']=str_replace('Foi configurada somente a conexão PDO. Symfony Dotenv carrega .env; não há ORM.','A conexao PDO abaixo e independente do ORM instalado por sua escolha.',$files['LEIA-ME.txt']);
            $files['LEIA-ME.txt']=str_replace('Você escolheu SEM BANCO. Nenhum componente de banco, .env ou classe de conexão foi gerado.','Nenhuma conexao automatica foi configurada. O pacote database foi instalado por sua escolha; configure sua conexao manualmente.',$files['LEIA-ME.txt']);
        }
        $files['LEIA-ME.txt']=str_replace('O perfil mínimo não configura rotas HTTP, autenticação nem proteção CSRF.','Recursos do perfil instalado estao descritos no inicio deste guia. Para endpoints novos,',$files['LEIA-ME.txt']);
        $files['LEIA-ME.txt'].="\nAJUDA DOS COMPONENTES\nCada pacote instalado inclui seu manual em vendor/fxfavalessa/fx-NOME/docs/index.html.\nComponentes personalizados sao instalados, mas integracoes de negocio permanecem manuais.\n";
        return $files;
    }
}
