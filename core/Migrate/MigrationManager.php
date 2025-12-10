<?php

namespace Pluto\Migrate;

use PDO;
use Pluto\ORM\Mysql\Database;

class MigrationManager
{
    protected PDO $pdo;
    protected string $migrationsPath;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $this->migrationsPath = BASE_PATH . '/storage/migrations';
        if(!\file_exists($this->migrationsPath)){
            mkdir($this->migrationsPath, 0755, true);
        }
    }

    public function applyMigrations()
    {
        $this->createMigrationsTable();
        $appliedMigrations = $this->getAppliedMigrations();

        $files = scandir($this->migrationsPath);
        $toApplyMigrations = array_diff($files, $appliedMigrations, ['.', '..']);

        if (empty($toApplyMigrations)) {
            $this->log("Everything is up to date.");
            return;
        }

        $batch = $this->getNextBatchNumber();
        $this->log("Batch number for new migrations: {$batch}");

        foreach ($toApplyMigrations as $migrationFile) {
            $instance = $this->resolveMigrationClass($migrationFile);
            if ($instance) {
                $this->log("Being implemented: $migrationFile");
                $instance->up();
                $this->saveMigration($migrationFile, $batch);
                $this->log("Applied: $migrationFile");
            }
        }
    }

    public function rollback()
    {
        $this->createMigrationsTable();
        $lastBatchMigrations = $this->getLastBatch();

        if (empty($lastBatchMigrations)) {
            $this->log("No migration to be reverted was found.");
            return;
        }

        $this->log("The last batch is being recalled...");
        foreach ($lastBatchMigrations as $migration) {
            $instance = $this->resolveMigrationClass($migration['migration']);
            if ($instance) {
                $this->log("Being withdrawn: {$migration['migration']}");
                $instance->down();
                $this->log("Withdrawn: {$migration['migration']}");
            }
        }

        $this->deleteMigrationsFromLastBatch();
        $this->log("The last batch has been successfully retrieved.");
    }

    public function createMigration(string $name)
    {
        $timestamp = date('Y_m_d_His');
        $filename = $this->migrationsPath . "/m{$timestamp}_{$name}.php";

        $className = implode('', array_map('ucfirst', explode('_', $name)));

        list($table, $operation) = $this->parseMigrationName($name);

        $stub = $this->getStub($className, $table, $operation);

        file_put_contents($filename, $stub);
        $this->log("Migration created: m{$timestamp}_{$name}.php");
    }

    protected function parseMigrationName(string $name): array
    {
        if (str_starts_with($name, 'create_')) {
            $table = preg_replace_callback('/^create_(\w+)_table$/', fn($m) => $m[1], $name);
            return [$table, 'create'];
        }

        if (str_starts_with($name, 'update_') || str_starts_with($name, 'add_') || str_starts_with($name, 'change_')) {
            $table = preg_replace_callback('/^(update|add|change)_.*_to_(\w+)_table$/', fn($m) => $m[2], $name);
            return [$table, 'table'];
        }

        if (str_starts_with($name, 'drop_')) {
            $table = preg_replace_callback('/^drop_(\w+)_table$/', fn($m) => $m[1], $name);
            return [$table, 'drop'];
        }

        return [null, 'create'];
    }

    protected function getStub(string $className, ?string $table, string $operation): string
    {
        $table = $table ?? '';

        if ($operation === 'create') {
            return <<<PHP
<?php

use Pluto\Migrate\Migration;
use Pluto\Migrate\Schema;
use Pluto\Migrate\Blueprint;

class {$className} implements Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
}
PHP;
        }

        if ($operation === 'table') {
            return <<<PHP
<?php

use Pluto\Migrate\Migration;
use Pluto\Migrate\Schema;
use Pluto\Migrate\Blueprint;

class {$className} implements Migration
{
    public function up(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
            //
        });
    }

    public function down(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
            //
        });
    }
}
PHP;
        }
        
        if ($operation === 'drop') {
            return <<<PHP
<?php

use Pluto\Migrate\Migration;
use Pluto\Migrate\Schema;
use Pluto\Migrate\Blueprint;

class {$className} implements Migration
{
    public function up(): void
    {
        Schema::dropIfExists('{$table}');
    }

    public function down(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }
}
PHP;
        }

        return <<<PHP
<?php

use Pluto\Migrate\Migration;
use Pluto\Migrate\Schema;
use Pluto\Migrate\Blueprint;

class {$className} implements Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
}
PHP;
    }

    protected function createMigrationsTable()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255),
                batch INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=INNODB;
        ");
    }

    protected function getAppliedMigrations(): array
    {
        $statement = $this->pdo->prepare("SELECT migration FROM migrations");
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    protected function saveMigration(string $migration, int $batch)
    {
        $statement = $this->pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)");
        $statement->execute(['migration' => $migration, 'batch' => $batch]);
    }

    protected function getLastBatch(): array
    {
        $statement = $this->pdo->prepare("SELECT * FROM migrations WHERE batch = (SELECT MAX(batch) FROM migrations)");
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function getNextBatchNumber(): int
    {
        $statement = $this->pdo->prepare("SELECT MAX(batch) as max_batch FROM migrations");
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return ($result['max_batch'] ?? 0) + 1;
    }

    protected function deleteMigrationsFromLastBatch()
    {
        $this->pdo->exec("DELETE FROM migrations WHERE batch = (SELECT MAX(batch) FROM (SELECT * FROM migrations) as m)");
    }

    protected function resolveMigrationClass(string $migrationFile): ?Migration
    {
        $path = $this->migrationsPath . '/' . $migrationFile;
        if (!file_exists($path)) {
            return null;
        }
        require_once $path;
        
        $className = pathinfo($migrationFile, PATHINFO_FILENAME);
        $className = implode('', array_map('ucfirst', explode('_', substr($className, 18))));

        if (class_exists($className)) {
            $instance = new $className();
            if ($instance instanceof Migration) {
                return $instance;
            }
        }
        return null;
    }

    protected function log(string $message)
    {
        echo '[' . date('Y-m-d H:i:s') . '] - ' . $message . PHP_EOL;
    }
}
