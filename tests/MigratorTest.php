<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Database\Migrator;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    public function testItMigratesAndRollsBackOneBatch(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fx-migrations-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);
        file_put_contents($directory . '/2026_01_01_000000_create_items_table.php', <<<'PHP'
<?php
use Fx\Framework\Database\Migration;
use Illuminate\Database\Schema\Builder;
return new class extends Migration {
    public function up(Builder $schema): void { $schema->create('items', fn ($table) => $table->id()); }
    public function down(Builder $schema): void { $schema->dropIfExists('items'); }
};
PHP);
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $connection = $capsule->getConnection();
        $migrator = new Migrator($connection, $directory);

        self::assertCount(1, $migrator->migrate());
        self::assertTrue($connection->getSchemaBuilder()->hasTable('items'));
        self::assertSame([], $migrator->migrate());
        self::assertCount(1, $migrator->rollback());
        self::assertFalse($connection->getSchemaBuilder()->hasTable('items'));
    }
}
