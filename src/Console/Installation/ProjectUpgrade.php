<?php
declare(strict_types=1);
namespace Fx\Framework\Console\Installation;
use RuntimeException;

final class ProjectUpgrade
{
    public function __construct(private readonly string $root) {}

    public function plan(array $components): array
    {
        $manifest = json_decode(file_get_contents($this->root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        if (isset($manifest['require']['fxfavalessa/fx-wordpress']) || is_file($this->root . '/plugin.php')) {
            throw new RuntimeException('Use module:install para componentes de plugins WordPress; nao converta o hospedeiro em aplicacao web.');
        }
        if (($manifest['autoload']['psr-4']['App\\'] ?? null) !== 'app/') {
            throw new RuntimeException('Configure autoload PSR-4 App\\ => app/ no composer.json antes de gerar a estrutura.');
        }
        $files = ProjectStructure::files($components);
        $resources = dirname(__DIR__, 3) . '/resources/setup';
        $files['fxartisan'] = file_get_contents($resources . '/fxartisan');
        if (in_array('admin', $components, true)) {
            foreach (['config/admin.php', 'configure.php'] as $name) $files[$name] = file_get_contents($resources . '/admin/' . $name);
            $files['config/modules.json'] = json_encode(['schema'=>1,'manifests'=>['vendor/fxfavalessa/fx-admin/fx-module.json']], JSON_THROW_ON_ERROR);
            $files['.env'] = "APP_SECURE_COOKIE=0\nFX_MAIL_ENABLED=0\nAPP_URL=\"\"\n";
        }
        $create = []; $preserve = [];
        foreach ($files as $name => $contents) {
            $path = $this->root . '/' . $name;
            for ($part = $path; $part !== $this->root; $part = dirname($part)) {
                if (is_link($part)) throw new RuntimeException('Link simbolico no destino: ' . $name);
                if ($part !== $path && file_exists($part) && !is_dir($part)) throw new RuntimeException('Diretorio bloqueado por arquivo: ' . $name);
            }
            if (file_exists($path)) {
                if (!is_file($path)) throw new RuntimeException('Destino nao e arquivo: ' . $name);
                if (in_array($name, ['bootstrap.php','bootstrap/app.php','public/index.php','server.php'], true)
                    && str_replace("\r\n", "\n", file_get_contents($path)) !== str_replace("\r\n", "\n", $contents)) {
                    throw new RuntimeException('Entrada personalizada preservada: ' . $name . '. Integre os templates manualmente; nenhum pacote foi instalado.');
                }
                $preserve[] = $name;
            } else { $create[$name] = $contents; }
        }
        return ['create'=>$create, 'preserve'=>$preserve];
    }

    public function publish(array $files): void
    {
        foreach ($files as $name => $contents) {
            $path = $this->root . '/' . $name;
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0750, true)) throw new RuntimeException('Falha ao criar pasta.');
            $handle = fopen($path, 'x');
            if ($handle === false) throw new RuntimeException('Arquivo surgiu durante instalacao; nao foi sobrescrito: ' . $name);
            try {
                if ($name === '.env') chmod($path,0600);
                if (fwrite($handle,$contents) !== strlen($contents)) throw new RuntimeException('Falha ao gravar: ' . $name);
            } finally { fclose($handle); }
        }
    }
}
