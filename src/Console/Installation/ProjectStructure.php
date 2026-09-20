<?php
declare(strict_types=1);
namespace Fx\Framework\Console\Installation;

final class ProjectStructure
{
    public static function files(array $components): array
    {
        $resources = dirname(__DIR__, 3) . '/resources/setup';
        $files = [];
        if (in_array('http', $components, true) || in_array('admin', $components, true)) {
            foreach (['bootstrap.php', 'bootstrap/app.php', 'public/index.php', 'server.php',
                'app/Controllers/HomeController.php', 'app/Services/Page.php', 'routes/web.php',
                'routes/api.php', 'resources/views/home.php'] as $name) {
                $files[$name] = file_get_contents($resources . '/structure/' . $name);
            }
            foreach (['app/Models', 'app/Requests', 'app/Middleware', 'routes/generated',
                'public/assets', 'storage/cache', 'storage/views', 'storage/logs', 'storage/tmp'] as $directory) {
                $files[$directory . '/.gitkeep'] = '';
            }
            $files['.htaccess'] = file_get_contents($resources . '/root.htaccess');
            $files['public/.htaccess'] = file_get_contents($resources . '/public.htaccess');
        }
        if (in_array('database', $components, true)) {
            $files['config/database.php'] = file_get_contents($resources . '/structure/config/database.php');
            $files['database/migrations/.gitkeep'] = '';
        }
        $files['LEIA-ME-ESTRUTURA.txt'] = file_get_contents($resources . '/structure.txt');
        return $files;
    }
}
