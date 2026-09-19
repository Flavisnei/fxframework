<?php
declare(strict_types=1);
namespace Fx\Framework\Tests;
use Fx\Framework\Admin\AdminUrl;
use Fx\Framework\Http\Request;
use PHPUnit\Framework\TestCase;
final class AdminUrlTest extends TestCase
{
    public function testConfiguredUrlUsesOnlyItsPath():void {
        self::assertSame('/app/public/index.php',AdminUrl::base(Request::create('/admin'),'https://example.test/app/public/index.php'));
        self::assertSame('',AdminUrl::base(Request::create('/admin'),'https://example.test/'));
        $this->expectException(\RuntimeException::class);AdminUrl::base(Request::create('/admin'),'https://user:secret@example.test/path');
    }
    public function testRecoveryAllowsSubdirectoriesAndRejectsOtherPaths():void {
        foreach(['/admin','/app/public/admin','/app/public/index.php/admin'] as $path)self::assertTrue(AdminUrl::recoveryPath($path));
        foreach(['/other','//admin','/../admin','/admin?x=1'] as $path)self::assertFalse(AdminUrl::recoveryPath($path));
    }
}
