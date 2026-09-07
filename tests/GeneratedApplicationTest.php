<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Console\Application as ConsoleApplication;
use Fx\Framework\Database\Migrator;
use Fx\Framework\Http\Kernel;
use Fx\Framework\Http\Request;
use Fx\Framework\View\View;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

final class GeneratedApplicationTest extends TestCase
{
    public function testGeneratedCrudRequiresRenderedTokenAndReturns404ForMissingModels(): void
    {
        $root = sys_get_temp_dir() . '/fx-generated-' . bin2hex(random_bytes(8));
        mkdir($root);
        $previous = getcwd();
        try {
            chdir($root);
            ob_start();
            try {
                $console = new ConsoleApplication();
                self::assertSame(0, $console->run(['fxartisan', 'app:init']));
                self::assertSame(0, $console->run(['fxartisan', 'make:crud', 'CsrfRecord']));
            } finally {
                ob_end_clean();
            }
            // A conexao e sempre descartavel, independente das variaveis do ambiente.
            file_put_contents($root . '/config/database.php', "<?php return ['driver' => 'sqlite', 'database' => ':memory:'];");
            require $root . '/app/Models/CsrfRecord.php';
            require $root . '/app/Requests/CsrfRecordRequest.php';
            require $root . '/app/Controllers/CsrfRecordController.php';
            $app = require $root . '/bootstrap/app.php';
            $connection = Capsule::connection();
            (new Migrator($connection, $root . '/database/migrations'))->migrate();
            $kernel = $app->make(Kernel::class);

            $request = Request::create('/csrf_records', 'POST', ['name' => 'Maria']);
            $request->headers->set('Accept', 'application/json');
            self::assertSame(403, $kernel->handle($request)->getStatusCode());
            self::assertSame(0, $connection->table('csrf_records')->count());

            $view = new View($root . '/resources/views', $root . '/storage/tmp', $root . '/storage/cache');
            $html = $view->render('csrf_records/form.tpl');
            self::assertSame(1, preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $html, $matches));
            $request = Request::create('/csrf_records', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            ], json_encode(['name' => 'Maria', '_csrf' => $matches[1], 'admin' => true]));
            $response = $kernel->handle($request);
            self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
            self::assertSame(1, $connection->table('csrf_records')->count());
            self::assertSame('Maria', json_decode($response->getContent(), true)['name']);

            $invalid = Request::create('/csrf_records', 'POST', ['name' => [], '_csrf' => $matches[1]]);
            $invalid->headers->set('Accept', 'application/json');
            self::assertSame(422, $kernel->handle($invalid)->getStatusCode());
            self::assertSame(1, $connection->table('csrf_records')->count());
            $missing = Request::create('/csrf_records/999');
            $missing->headers->set('Accept', 'application/json');
            self::assertSame(404, $kernel->handle($missing)->getStatusCode());
        } finally {
            if (is_string($previous)) { chdir($previous); }
            // Apenas a arvore temporaria criada por este teste.
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }
}
