<?php
declare(strict_types=1);
namespace Fx\Framework\Console\Commands;
use Fx\Framework\Console\Installation\{SetupProfile, PackageCatalog, ProjectUpgrade, ComposerInstaller};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class SetupUpgradeCommand extends Command
{
    public function __construct(private readonly string $root) { parent::__construct('setup:upgrade'); }
    protected function configure(): void
    {
        $this->setDescription('Acrescenta estrutura e componentes a um projeto existente sem sobrescrever arquivos')
            ->addOption('target',null,InputOption::VALUE_REQUIRED,'Raiz do projeto existente; padrao e o projeto atual')
            ->addOption('profile',null,InputOption::VALUE_REQUIRED,'complete ou custom','complete')
            ->addOption('components',null,InputOption::VALUE_REQUIRED,'Componentes separados por virgula para custom')
            ->addOption('without-admin',null,InputOption::VALUE_NONE,'Completa sem adicionar painel padrao')
            ->addOption('constraint',null,InputOption::VALUE_REQUIRED,'Versao dos pacotes adicionados',PackageCatalog::PUBLISHED_VERSION)
            ->addOption('composer',null,InputOption::VALUE_REQUIRED,'Caminho de composer.phar')
            ->addOption('dry-run',null,InputOption::VALUE_NONE,'Mostra plano local sem gravar ou acessar rede')
            ->addOption('yes',null,InputOption::VALUE_NONE,'Confirma instalacao do plano')
            ->setHelp('Preserva .env, dados e arquivos existentes. Recusa entradas web personalizadas antes de instalar. Nao remove pacotes, cria contas ou executa migrations. Na minima sem Console use o fxartisan do framework com --target. Consulte LEIA-ME-ESTRUTURA.txt e a ajuda HTML.');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = realpath($input->getOption('target') ?? $this->root);
        if ($root === false || !is_file($root . '/composer.json')) throw new \RuntimeException('Informe um projeto existente com composer.json.');
        $profile = $input->getOption('profile');
        if (!in_array($profile,['complete','custom'],true)) throw new \RuntimeException('Use complete ou custom; a atualizacao e aditiva.');
        $chosen = $input->getOption('components') === null ? [] : array_values(array_filter(array_map('trim',explode(',',$input->getOption('components')))));
        if ($profile === 'custom' && $chosen === []) throw new \RuntimeException('Informe --components para custom.');
        $components = SetupProfile::components($profile,$chosen,(bool)$input->getOption('without-admin'));
        // O comando torna o Console disponivel localmente para as proximas etapas.
        $components = array_values(array_unique([...$components,'console']));
        $upgrade = new ProjectUpgrade($root);
        $plan = $upgrade->plan($components);
        $output->writeln('Destino: ' . $root, OutputInterface::OUTPUT_RAW);
        $output->writeln('Adicionar componentes: ' . implode(', ',$components));
        foreach (array_keys($plan['create']) as $name) $output->writeln('Criar: ' . $name);
        foreach ($plan['preserve'] as $name) $output->writeln('Preservar: ' . $name);
        if ($input->getOption('dry-run')) return self::SUCCESS;
        if (!$input->getOption('yes')) {
            if (!$input->isInteractive()) throw new \RuntimeException('Use --dry-run para revisar e --yes para aplicar.');
            if (!$this->getHelper('question')->ask($input,$output,new ConfirmationQuestion('Aplicar este plano? [s/N] ',false,'/^(s|sim|y|yes)/i'))) return self::SUCCESS;
        }
        $status = (new ComposerInstaller($root))->install(array_map(PackageCatalog::package(...),$components),$input->getOption('constraint'),$input->getOption('composer'),false,$output,null,true);
        if ($status !== 0) return $status;
        // Revalida caminhos apos o Composer e antes de publicar os arquivos.
        $plan = $upgrade->plan($components);
        $upgrade->publish($plan['create']);
        $output->writeln('Estrutura adicionada. Leia LEIA-ME-ESTRUTURA.txt e revise os arquivos preservados.');
        if (in_array('admin',$components,true)) $output->writeln('Configure o painel com php configure.php; confira config/modules.json se ja existia.');
        return self::SUCCESS;
    }
}
