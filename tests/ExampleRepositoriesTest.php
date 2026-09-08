<?php
declare(strict_types=1);
namespace Fx\Framework\Tests;

use PHPUnit\Framework\TestCase;

final class ExampleRepositoriesTest extends TestCase
{
    public function testPathPackagesHaveStableDevelopmentIdentityWithoutAGitBranch(): void
    {
        // Sem options.versions, um checkout destacado gera dev-HASH e quebra
        // dependencias internas que aceitam ^1.0 || dev-main.
        $examples=glob(dirname(__DIR__) . '/examples/*/composer.json');
        self::assertNotEmpty($examples);
        foreach ($examples as $file) {
            $manifest=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
            foreach ($manifest['repositories'] ?? [] as $repository) {
                if (($repository['type'] ?? null)!=='path') continue;
                $packages=glob(dirname($file) . '/' . $repository['url'],GLOB_ONLYDIR);
                self::assertNotEmpty($packages,$file);
                foreach ($packages as $package) {
                    $name=json_decode(file_get_contents($package . '/composer.json'),true,512,JSON_THROW_ON_ERROR)['name'];
                    self::assertSame('dev-main',$repository['options']['versions'][$name] ?? null, $file . ': ' . $name);
                }
            }
        }
    }
}
