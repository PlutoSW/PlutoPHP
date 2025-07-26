<?php

namespace Pluto\Core\CMD\MigrationGenerator;


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

        $this->getTableNameFromMigrationName();

        if ($this->createTableName) {
            $this->schema = Schema::create($this->tableName, function (Table $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->comment("Description of the table");
                $table->timestamps();
                $table->engine('InnoDB');
                $table->charset('utf8mb4');
                $table->collation('utf8mb4_unicode_ci');
                $table->comment("Table created by migration: {$this->migrationName}");
            });
        }

        if ($this->updateTableName) {
            $this->schema = Schema::update($this->tableName, function (Table $table) {
                $table->string('name')->nullable();
                $table->string('description')->comment("Updated description of the table");
            });
        }

        if ($this->deleteTableName) {
            $this->schema = Schema::drop($this->tableName);
        }
    }

    public function getSqlString(): string
    {
        if (!$this->schema) {
            throw new \Exception("Schema not created. Please provide a valid migration name.");
        }
        return $this->schema::$sqlString;
    }

    private function getTableNameFromMigrationName()
    {
        match (true) {
            $this->isCreateMigration() => $this->tableName = $this->createTableName,
            $this->isUpdateMigration() => $this->tableName = $this->updateTableName,
            $this->isDropMigration() => $this->tableName = $this->deleteTableName,
            default => false
        };
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
