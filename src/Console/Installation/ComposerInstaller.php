<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Installation;

use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

final class ComposerInstaller
{
    public function __construct(private readonly string $root) {}

    public function install(array $packages, string $constraint, ?string $composer, bool $dryRun, OutputInterface $output, ?InstallCatalog $catalog = null): int
    {
        if (!is_file($this->root . '/composer.json')) {
            throw new RuntimeException('composer.json ausente. Execute na raiz da aplicacao; consulte a ajuda HTML do Console.');
        }
        // Evita que o ambiente redirecione a instalacao para outro manifesto/vendor.
        foreach (['COMPOSER', 'COMPOSER_VENDOR_DIR'] as $variable) {
            if (getenv($variable) !== false && getenv($variable) !== '') {
                throw new RuntimeException("Remova {$variable} do ambiente antes de instalar pelo Artisan.");
            }
        }
        if ($constraint === '' || strlen($constraint) > 200 || preg_match('/[\x00-\x1f\x7f:]/', $constraint)) {
            throw new RuntimeException('Restricao de versao invalida. Exemplo: ^1.0 ou @dev para desenvolvimento local.');
        }
        $requirements = [];
        foreach ($packages as $package) {
            $requirements[] = ($catalog === null ? PackageCatalog::package($package) : $catalog->package($package)) . ':' . $constraint;
        }
        $command = [...$this->executable($composer), 'require', '--no-interaction', '--no-plugins', '--no-scripts'];
        if ($dryRun) { $command[] = '--dry-run'; }
        array_push($command, ...$requirements);
        $output->writeln(($dryRun ? 'Simulacao: ' : 'Instalacao: ') . implode(', ', $requirements), OutputInterface::OUTPUT_RAW);
        // Argumentos separados, sem shell. stdout/stderr compartilham um pipe para evitar deadlock.
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $this->root, null, ['bypass_shell' => true]);
        if (!is_resource($process)) { throw new RuntimeException('Nao foi possivel executar Composer.'); }
        fclose($pipes[0]);
        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 8192);
            if ($chunk === false) { break; }
            $output->write($chunk, false, OutputInterface::OUTPUT_RAW);
        }
        fclose($pipes[1]);
        $status = proc_close($process);
        if ($status !== 0) {
            $output->writeln('Composer falhou. Confira a saida e o estado de composer.json, composer.lock e vendor antes de tentar novamente.');
            return 1;
        }
        $output->writeln($dryRun
            ? 'Simulacao concluida. Nenhum pacote foi instalado no projeto.'
            : 'Pacotes instalados. Execute o Artisan novamente para carregar os novos comandos. Consulte a ajuda dos pacotes para configurar seu uso.');
        return 0;
    }

    private function executable(?string $explicit): array
    {
        if ($explicit !== null) {
            $resolved = realpath($explicit);
            if ($resolved === false || !is_file($resolved)) { throw new RuntimeException('Caminho de Composer inexistente. Informe --composer com o caminho do composer.phar.'); }
            return $this->executableCommand($resolved);
        }
        // No Windows o wrapper .bat usa um shell; preferimos o PHAR adjacente.
        foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $directory) {
            $directory = trim($directory, '"');
            if ($directory === '') { continue; }
            $names = PHP_OS_FAMILY === 'Windows' ? ['composer.phar', 'composer.exe'] : ['composer', 'composer.phar'];
            foreach ($names as $name) {
                $candidate = rtrim($directory, '/\\') . '/' . $name;
                if (is_file($candidate)) { return $this->executableCommand($candidate); }
            }
        }
        throw new RuntimeException('Composer nao encontrado no PATH. Informe --composer=C:/caminho/composer.phar (ou executavel Composer no Unix).');
    }

    private function executableCommand(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, ['bat', 'cmd'], true)) {
            throw new RuntimeException('Use o composer.phar ao lado do wrapper .bat/.cmd.');
        }
        return $extension === 'phar' ? [PHP_BINARY, $path] : [$path];
    }
}
