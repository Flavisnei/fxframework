<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Console\Application;
use PHPUnit\Framework\TestCase;

final class ConsoleApplicationTest extends TestCase
{
    public function testCacheClearRemovesNestedFilesAndPreservesGitkeep(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fx-console-' . bin2hex(random_bytes(6));
        $cache = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        mkdir($cache . DIRECTORY_SEPARATOR . 'nested', 0777, true);
        file_put_contents($cache . DIRECTORY_SEPARATOR . '.gitkeep', '');
        file_put_contents($cache . DIRECTORY_SEPARATOR . 'root.cache', 'cache');
        file_put_contents($cache . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'item.cache', 'cache');

        $previousDirectory = getcwd();

        try {
            chdir($root);
            ob_start();
            $status = (new Application())->run(['fxartisan', 'cache:clear']);
            ob_end_clean();
        } finally {
            if (is_string($previousDirectory)) {
                chdir($previousDirectory);
            }
        }

        self::assertSame(0, $status);
        self::assertFileExists($cache . DIRECTORY_SEPARATOR . '.gitkeep');
        self::assertFileDoesNotExist($cache . DIRECTORY_SEPARATOR . 'root.cache');
        self::assertDirectoryDoesNotExist($cache . DIRECTORY_SEPARATOR . 'nested');
    }
}
