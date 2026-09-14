<?php
declare(strict_types=1);
namespace Fx\Framework\Console\Commands;

use Fx\Framework\Console\Installation\MinimalSetup;
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
        $this->setDescription('Assistente de instalacao minima em uma NOVA pasta; banco PDO opcional')
            ->addArgument('target', InputArgument::REQUIRED, 'Nova pasta do projeto (o pai deve existir)')
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
        $driver = $input->getOption('database');
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
        $output->writeln('Perfil: minimo. Destino: ' . $target,OutputInterface::OUTPUT_RAW);
        $output->writeln($db === null ? 'Sem banco: apenas Core; sem .env ou componente de banco.' : 'Banco: ' . $driver . '. Somente PDO + Dotenv para ler .env. Sem ORM, migrations ou tabelas.');
        $output->writeln('Serao criados: composer.json, app/Saudacao.php, example.php, LEIA-ME.txt e ajuda HTML.' . ($db === null ? '' : ' Tambem .env, .env.example e app/Connection.php.'));
        if ($input->getOption('dry-run')) { $output->writeln('Simulacao concluida. Nenhum arquivo, conexao ou download realizado.'); return self::SUCCESS; }
        if (!$input->getOption('yes')) {
            if (!$input->isInteractive()) { throw new \RuntimeException('Revise com --dry-run e use --yes para executar sem interacao.'); }
            if (!$ask(new ConfirmationQuestion('Confirmar instalacao? [s/N] ',false,'/^(s|sim|y|yes)/i'))) { $output->writeln('Cancelado. Nenhum arquivo criado.'); return self::SUCCESS; }
        }
        if ($db !== null) { MinimalSetup::testDatabase($db); $output->writeln('Conexao testada; nenhuma tabela criada.'); }
        $path = (new MinimalSetup())->create($target,$db,$input->getOption('core-path'));
        $output->writeln('Projeto gerado. Credenciais nao foram incluidas no LEIA-ME.txt.');
        if ($input->getOption('no-install')) { $output->writeln('Entre na nova pasta e execute composer install --no-plugins --no-scripts; depois php example.php.'); return self::SUCCESS; }
        return (new ComposerInstaller($path))->installProject($input->getOption('composer'),$output);
    }
}
