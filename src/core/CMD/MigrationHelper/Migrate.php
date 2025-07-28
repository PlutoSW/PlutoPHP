<?php

namespace Pluto\Core\CMD\MigrationHelper;

use Pluto\Core\Storage;

class Migrate
{

    private $storage;
    private $migrationFiles;
    private $notRunMigrations;
    private $runnedMigrations;
    private $rolledBackMigrations;

    public function __construct()
    {
        $this->storage = new Storage();
        $this->storage->setPath('migrations');
        $this->migrationFiles = $this->storage->files('*.php');
    }

    public function up()
    {
        $notRunMigrations = $this->notRunMigrations();
        foreach ($notRunMigrations as $migrationFile) {
            require_once $this->storage->getPath() . '/' . $migrationFile;
            $className = 'Pluto\\Core\\CMD\\MigrationHelper\\' . pathinfo($migrationFile, PATHINFO_FILENAME);
            $migrationInstance = new $className($migrationFile);
            $migrationInstance->up();
            $this->storage->setContents($migrationFile, str_replace('//migration_status=not_run', '//migration_status=runned', file_get_contents($this->storage->getPath() . '/' . $migrationFile)));
        }
    }

    public function down()
    {
        $runnedMigrations = $this->runnedMigrations();
        foreach ($runnedMigrations as $migrationFile) {
            require_once $this->storage->getPath() . '/' . $migrationFile;
            $className = 'Pluto\\Core\\CMD\\MigrationHelper\\' . pathinfo($migrationFile, PATHINFO_FILENAME);
            $migrationInstance = new $className($migrationFile);
            $migrationInstance->down();
            $this->storage->setContents($migrationFile, str_replace('//migration_status=runned', '//migration_status=rolled_back', file_get_contents($this->storage->getPath() . '/' . $migrationFile)));
        }
    }

    private function notRunMigrations()
    {
        $this->notRunMigrations = [];
        foreach ($this->migrationFiles as $file) {
            $content = $this->storage->getContents($file);
            if (strpos($content, 'migration_status=created') !== false) {
                $this->notRunMigrations[] = $file;
            }
        }
        return $this->notRunMigrations;
    }

    private function runnedMigrations()
    {
        $this->runnedMigrations = [];
        foreach ($this->migrationFiles as $file) {
            $content = $this->storage->getContents($file);
            if (strpos($content, 'migration_status=runned') !== false) {
                $this->runnedMigrations[] = $file;
            }
        }
        return $this->runnedMigrations;
    }

    private function rollbackedMigrations()
    {
        $this->rolledBackMigrations = [];
        foreach ($this->migrationFiles as $file) {
            $content = $this->storage->getContents($file);
            if (strpos($content, 'migration_status=rolled_back') !== false) {
                $this->rolledBackMigrations[] = $file;
            }
        }
        return $this->rolledBackMigrations;
    }
}
