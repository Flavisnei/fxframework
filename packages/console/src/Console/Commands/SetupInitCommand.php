<?php
declare(strict_types=1);
namespace Fx\Framework\Console\Commands;

use Fx\Framework\Console\Installation\MinimalSetup;
use Fx\Framework\Console\Installation\SetupProfile;
use Fx\Framework\Console\Installation\PackageCatalog;
use Fx\Framework\Console\Installation\ComposerInstaller;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class SetupInitCommand extends Command
{
    public function __construct() { parent::__construct('setup:init'); }
    protected function configure(): void
    {
        $this->setDescription('Assistente de instalacao em uma NOVA pasta: escolha o perfil')
            ->addArgument('target', InputArgument::REQUIRED, 'Nova pasta do projeto (o pai deve existir)')
            ->addOption('profile',null,InputOption::VALUE_REQUIRED,'minimal, complete, custom ou wordpress')
            ->addOption('without-admin',null,InputOption::VALUE_NONE,'Completa sem painel padrao; conserva a estrutura e os demais componentes')
            ->addOption('components',null,InputOption::VALUE_REQUIRED,'Componentes separados por virgula no perfil custom')
            ->addOption('database',null,InputOption::VALUE_REQUIRED,'none, mariadb, mysql ou sqlite; sem opcao, pergunta')
            ->addOption('db-host',null,InputOption::VALUE_REQUIRED,'Servidor do banco')
            ->addOption('db-port',null,InputOption::VALUE_REQUIRED,'Porta do banco')
            ->addOption('db-name',null,InputOption::VALUE_REQUIRED,'Banco existente ou arquivo SQLite existente')
            ->addOption('db-user',null,InputOption::VALUE_REQUIRED,'Usuario do banco')
            ->addOption('db-password-env',null,InputOption::VALUE_REQUIRED,'NOME da variavel de ambiente contendo a senha; nunca a senha em argumento')
            ->addOption('db-no-password',null,InputOption::VALUE_NONE,'Confirma explicitamente que o banco nao tem senha')
            ->addOption('core-path',null,InputOption::VALUE_REQUIRED,'Pacote Core local para desenvolvimento; opcional')
            ->addOption('composer',null,InputOption::VALUE_REQUIRED,'Caminho do composer.phar')
            ->addOption('no-install',null,InputOption::VALUE_NONE,'Gera arquivos; instale dependencias depois com composer install')
            ->addOption('dry-run',null,InputOption::VALUE_NONE,'Mostra plano sem gravar, conectar ao banco ou instalar')
            ->addOption('yes',null,InputOption::VALUE_NONE,'Aceita o resumo final; necessario com --no-interaction');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = MinimalSetup::validateTarget((string)$input->getArgument('target'));
        $ask = fn(Question $question) => $this->getHelper('question')->ask($input,$output,$question);
        $profile=$input->getOption('profile');
        if($profile===null) {
            $profile='minimal';
            if($input->isInteractive()) {
                $label=$ask(new ChoiceQuestion('Qual nivel de instalacao deseja?',[1=>'Minima',2=>'Completa',3=>'Personalizada',4=>'WordPress'],1));
                $profile=array_search($label,SetupProfile::LABELS,true);
            }
        }
        $components=[];
        if($input->getOption('components')!==null)$components=array_values(array_filter(array_map('trim',explode(',',$input->getOption('components')))));
        elseif($profile==='custom' && $input->isInteractive()) {
            $choices=array_values(array_diff(array_keys(PackageCatalog::COMPONENTS),['core','wordpress']));
            foreach($choices as $name)$output->writeln($name.': '.PackageCatalog::COMPONENTS[$name]);
            $question=(new ChoiceQuestion('Escolha componentes (indices ou nomes separados por virgula); core sempre incluido',$choices))->setMultiselect(true);
            $components=$ask($question);
        }
        $withoutAdmin=(bool)$input->getOption('without-admin');
        if($profile==='complete' && !$withoutAdmin && $input->isInteractive())$withoutAdmin=!$ask(new ConfirmationQuestion('Incluir o painel padrao FX Admin? [S/n] ',true,'/^(s|sim|y|yes)/i'));
        $selected=SetupProfile::components($profile,$components,$withoutAdmin);
        $admin=in_array('admin',$selected,true);
        $driver = $input->getOption('database');
        if($profile==='wordpress') {
            if($driver!==null && $driver!=='none')throw new \RuntimeException('WordPress usa o banco do hospedeiro, sem conexao separada.');
            $driver='none';
        }
        if($admin && $driver===null) {
            $driver=$input->isInteractive() ? ($ask(new ConfirmationQuestion('Deseja banco externo? [s/N] N usa SQLite local do painel. ',false,'/^(s|sim|y|yes)/i')) ? $ask(new ChoiceQuestion('Banco existente: ',['mariadb','mysql','sqlite'],0)) : 'none') : 'none';
        }

        if ($driver === null) {
            if (!$input->isInteractive()) { throw new \RuntimeException('Informe --database=none ou um banco explicitamente.'); }
            $driver = $ask(new ConfirmationQuestion('Deseja configurar uma conexao com banco de dados? [s/N] ',false,'/^(s|sim|y|yes)/i'))
                ? $ask(new ChoiceQuestion('Escolha o banco (deve existir): ',['mariadb','mysql','sqlite'],0)) : 'none';
        }
        if (!in_array($driver,['none','mariadb','mysql','sqlite'],true)) { throw new \RuntimeException('Banco invalido: use none, mariadb, mysql ou sqlite.'); }
        $db = null;
        if ($driver !== 'none') {
            $field = function(string $option,string $label,string $default='')use($input,$ask):string {
                $value = $input->getOption($option);
                if ($value !== null) return (string)$value;
                return $input->isInteractive() ? (string)$ask(new Question($label . ($default !== '' ? ' ['.$default.']' : '') . ': ', $default)) : $default;
            };
            $db = ['driver'=>$driver,'host'=>$driver === 'sqlite' ? '' : $field('db-host','Servidor','127.0.0.1'),'port'=>$driver === 'sqlite' ? '' : $field('db-port','Porta','3306'),'database'=>$field('db-name',$driver === 'sqlite' ? 'Arquivo SQLite existente' : 'Nome do banco existente'),'username'=>$driver === 'sqlite' ? '' : $field('db-user','Usuario','root'),'password'=>''];
            if ($driver === 'sqlite') { $db['database'] = realpath($db['database']) ?: $db['database']; }
            else {
                $variable = $input->getOption('db-password-env');
                if ($input->getOption('db-no-password')) {
                    if ($variable !== null) throw new \RuntimeException('Escolha --db-no-password ou --db-password-env.');
                } elseif ($variable !== null) {
                    if (!preg_match('/\A[A-Z_][A-Z0-9_]*\z/', $variable) || getenv($variable) === false) { throw new \RuntimeException('Variavel de senha ausente ou nome invalido.'); }
                    $db['password'] = getenv($variable);
                } elseif ($input->isInteractive()) {
                    $question = (new Question('Senha do banco (vazia se nao houver): ',''))->setHidden(true)->setHiddenFallback(false);
                    $db['password'] = (string)$ask($question);
                } else { throw new \RuntimeException('Informe --db-password-env com uma variavel definida, ou use --db-no-password para banco sem senha.'); }
            }
            MinimalSetup::validateDatabase($db);
        }
        $output->writeln('Perfil: '.SetupProfile::LABELS[$profile].'. Destino: ' . $target,OutputInterface::OUTPUT_RAW);
        $output->writeln('Componentes diretos: '.implode(', ',$selected).'. Composer resolve as dependencias.');
        $output->writeln($db === null ? ($admin ? 'Painel: SQLite local; configure.php criara tabelas e administrador.' : ($profile==='wordpress' ? 'Banco e usuarios do WordPress hospedeiro.' : 'Sem conexao de banco configurada.')) : 'Conexao PDO: ' . $driver . '. Tabelas administrativas somente ao executar configure.php, se houver Admin.');
        if(in_array('database',$selected,true))$output->writeln('Eloquent e migrations incluidos; configure .env antes de usar o ORM.');
        $output->writeln('Serao criados: composer.json, app/Saudacao.php, example.php, LEIA-ME.txt e ajuda HTML.' . ($db === null ? '' : ' Tambem .env, .env.example e app/Connection.php.'));
        if($admin)$output->writeln('Tambem: bootstrap.php, config/admin.php, public/index.php e configure.php. Administrador sera criado no passo configure.php.');
        elseif($profile==='wordpress')$output->writeln('Tambem: plugin.php para ativacao no WordPress.');
        elseif(in_array('http',$selected,true))$output->writeln('Tambem: controllers, services, views, rotas web/API e bootstrap/app.php.');
        if ($input->getOption('dry-run')) { $output->writeln('Simulacao concluida. Nenhum arquivo, conexao ou download realizado.'); return self::SUCCESS; }
        if (!$input->getOption('yes')) {
            if (!$input->isInteractive()) { throw new \RuntimeException('Revise com --dry-run e use --yes para executar sem interacao.'); }
            if (!$ask(new ConfirmationQuestion('Confirmar instalacao? [s/N] ',false,'/^(s|sim|y|yes)/i'))) { $output->writeln('Cancelado. Nenhum arquivo criado.'); return self::SUCCESS; }
        }
        if ($db !== null) { MinimalSetup::testDatabase($db); $output->writeln('Conexao testada; nenhuma tabela criada.'); }
        $path = (new MinimalSetup())->create($target,$db,$input->getOption('core-path'),$profile,$components,$withoutAdmin);
        $output->writeln('Projeto gerado. Credenciais nao foram incluidas no LEIA-ME.txt.');
        if ($input->getOption('no-install')) { $output->writeln('Entre na nova pasta e execute composer install --no-plugins --no-scripts; consulte LEIA-ME.txt.' . ($admin ? ' Depois execute php configure.php.' : '')); return self::SUCCESS; }
        $status=(new ComposerInstaller($path))->installProject($input->getOption('composer'),$output);
        if($status===0 && $admin) {
            $output->writeln('Proximo passo: php configure.php na pasta gerada para criar o administrador e ativar o painel.');
        }
        if($status===0 && $profile==='wordpress')$output->writeln('Copie a pasta com vendor para wp-content/plugins e ative FX Projeto no WordPress.');
        return $status;
    }
}
