<?php

namespace Pluto\Core\CMD\MigrationHelper;

use Pluto\Core\Storage;

class MG
{

    private $migrationName;
    private $tableName;
    private $createTableName;
    private $updateTableName;
    private $deleteTableName;

    private $schema;

    public function __construct($migrationName)
    {
        $this->migrationName = $migrationName;

        match (true) {
            $this->isCreateMigration() => $this->createMigration(),
            $this->isUpdateMigration() => $this->updateMigration(),
            $this->isDropMigration() => $this->dropMigration(),
            default => $this->generalMigration(),
        };
    }

    public function createMigration()
    {
        $storage = new Storage();
        $storage->setPath("migrations");

        $upTemplate = <<<PHP
        Schema::create('{$this->createTableName}', function (Table \$table) {
                    \$table->id();
                    \$table->string('name')->nullable();
                });
        PHP;

        $downTemplate = <<<PHP
        Schema::drop('{$this->createTableName}');
        PHP;

        $storage->setContents($this->migrationName . '.php', "<?php\n\nnamespace Pluto\Core\CMD\MigrationHelper;\n//migration_status=created\nuse Pluto\Core\CMD\MigrationHelper\Schema;\nuse Pluto\Core\CMD\MigrationHelper\Table;\n\nclass {$this->migrationName}\n{\n    public function up()\n    {\n        $upTemplate\n    }\n\n    public function down()\n    {\n        $downTemplate\n    }\n}\n");
    }

    public function updateMigration()
    {
        $storage = new Storage();
        $storage->setPath("migrations");

        $upTemplate = <<<PHP
        Schema::update('{$this->updateTableName}', function (Table \$table) {
                    \$table->string('updated_column')->nullable();
                });
        PHP;
        $downTemplate = <<<PHP
        Schema::update('{$this->updateTableName}', function (Table \$table) {
                    \$table->dropColumn('updated_column');
                });
        PHP;

        $storage->setContents($this->migrationName . '.php', "<?php\n\nnamespace Pluto\Core\CMD\MigrationHelper;\n//migration_status=created\nuse Pluto\Core\CMD\MigrationHelper\Schema;\nuse Pluto\Core\CMD\MigrationHelper\Table;\n\nclass {$this->migrationName}\n{\n    public function up()\n    {\n        $upTemplate\n    }\n\n    public function down()\n    {\n        $downTemplate\n    }\n}\n");
    }

    public function dropMigration()
    {
        $storage = new Storage();
        $storage->setPath("migrations");
        $template = <<<PHP
        Schema::drop('{$this->deleteTableName}');
        PHP;
        $storage->setContents($this->migrationName . '.php', "<?php\n\nnamespace Pluto\Core\CMD\MigrationHelper;\n//migration_status=created\nuse Pluto\Core\CMD\MigrationHelper\Schema;\n\nclass {$this->migrationName}\n{\n    public function up()\n    {\n        $template\n    }\n\n    public function down()\n    {\n        // Implement your rollback logic here\n    }\n}\n");
    }

    public function generalMigration()
    {
        echo "General migration logic for: {$this->migrationName}" . PHP_EOL;
        // Implement general migration logic here if needed
        // For now, we just print the migration name
        $this->schema = new Schema();
    }

    public function getSqlString(): string
    {
        if (!$this->schema) {
            throw new \Exception("Schema not created. Please provide a valid migration name.");
        }
        return $this->schema::$sqlString;
    }

    private function isCreateMigration(): bool
    {

        if (strpos($this->migrationName, 'create') === 0) {
            $this->createTableName = str_replace(['create_', '_table'], '', $this->migrationName);
            return true;
        }
        return false;
    }

    private function isUpdateMigration(): bool
    {
        if (strpos($this->migrationName, 'update') === 0) {
            $this->updateTableName = str_replace(['update_', '_table'], '', $this->migrationName);
            return true;
        }
        return false;
    }

    private function isDropMigration(): bool
    {
        if (strpos($this->migrationName, 'drop') === 0) {
            $this->deleteTableName = str_replace(['drop_', '_table'], '', $this->migrationName);
            return true;
        }
        return false;
    }
}
