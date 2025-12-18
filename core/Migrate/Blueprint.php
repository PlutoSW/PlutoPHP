<?php

namespace Pluto\Migrate;

use Pluto\Migrate\ColumnDefinition;
use Pluto\Migrate\ForeignKeyDefinition;

class Blueprint
{
    protected string $table;
    protected bool $isAlter;
    protected array $columns = [];
    protected array $commands = [];

    public function __construct(string $table, bool $isAlter = false)
    {
        $this->table = $table;
        $this->isAlter = $isAlter;
    }

    protected function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        $column = new ColumnDefinition(array_merge(compact('type', 'name'), $parameters));
        $this->columns[] = $column;
        return $column;
    }

    public function id(string $column = 'id'): ColumnDefinition
    {
        return $this->unsignedBigInteger($column)->autoIncrement()->primary();
    }

    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('varchar', $column, compact('length'));
    }

    public function enum(string $column, array $values): ColumnDefinition
    {
        return $this->addColumn('enum', $column, compact('values'));
    }

    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn('int', $column);
    }

    public function decimal(string $column, int $precision, int $scale = 0): ColumnDefinition
    {
        return $this->addColumn('decimal', $column, compact('precision', 'scale'));
    }
    
    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('bigint', $column);
    }

    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        return $this->bigInteger($column)->unsigned();
    }

    public function foreignId(string $column): ColumnDefinition
    {
        return $this->unsignedBigInteger($column);
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyint', $column, ['length' => 1]);
    }

    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn('timestamp', $column);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable()->default('CURRENT_TIMESTAMP');
        $this->timestamp('updated_at')->nullable()->default('CURRENT_TIMESTAMP')->onUpdate('CURRENT_TIMESTAMP');
    }

    public function softDeletes(string $column = 'deleted_at'): ColumnDefinition
    {
        return $this->timestamp($column)->nullable();
    }

    public function foreign(string|array $columns): ForeignKeyDefinition
    {
        $command = $this->addCommand('foreign', ['columns' => (array) $columns]);
        return $command->parameters['definition'] = new ForeignKeyDefinition(['columns' => (array) $columns]);
    }

    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn('date', $column);
    }

    public function dropColumn(string|array $columns): void
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        $this->addCommand('dropColumn', compact('columns'));
    }

    public function renameColumn(string $from, string $to): void
    {
        $this->addCommand('renameColumn', compact('from', 'to'));
    }

    public function change(): ColumnDefinition
    {
        if (!empty($this->columns)) {
            $column = end($this->columns);
            $column->change();
        }
        return end($this->columns);
    }

    protected function addCommand(string $name, array $parameters = []): object
    {
        $command = (object) compact('name', 'parameters');
        $this->commands[] = $command;
        return $command;
    }

    public function primary($columns): void
    {
        $this->addCommand('primary', ['columns' => (array) $columns]);
    }

    public function unique($columns): void
    {
        $this->addCommand('unique', ['columns' => (array) $columns]);
    }

    public function index($columns): void
    {
        $this->addCommand('index', ['columns' => (array) $columns]);
    }

    public function dropPrimary($index = null): void
    {
        $this->addCommand('dropPrimary', compact('index'));
    }

    public function dropUnique($index): void
    {
        $this->addCommand('dropUnique', compact('index'));
    }

    public function dropIndex($index): void
    {
        $this->addCommand('dropIndex', compact('index'));
    }

    public function toSql(): string
    {
        if ($this->isAlter) {
            return $this->buildAlter();
        }

        return $this->buildCreate();
    }

    protected function buildCreate(): string
    {
        $definitions = array_map([$this, 'buildColumnDefinition'], $this->columns);
        
        $primaryKey = null;
        foreach ($this->columns as $column) {
            if ($column->primary && $column->autoIncrement) {
                $primaryKey = $column->name;
            }
        }
        if ($primaryKey) {
            $definitions[] = "PRIMARY KEY (`{$primaryKey}`)";
        }

        $indexDefinitions = [];
        foreach ($this->commands as $command) {
            if (in_array($command->name, ['primary', 'unique', 'index', 'foreign'])) {
                $indexDefinitions[] = $this->buildCommandDefinition($command);
            }
        }
        $definitions = array_merge($definitions, array_filter($indexDefinitions));

        return "CREATE TABLE `{$this->table}` (\n    " .
            implode(",\n    ", $definitions) .
            "\n) ENGINE=INNODB;";
    }

    protected function buildAlter(): string
    {
        $statements = [];
        foreach ($this->columns as $column) {
            $definition = $this->buildColumnDefinition($column);
            if ($column->change) {
                $statements[] = "ALTER TABLE `{$this->table}` MODIFY COLUMN {$definition}";
            } else {
                $statements[] = "ALTER TABLE `{$this->table}` ADD COLUMN {$definition}";
            }
        }

        foreach ($this->commands as $command) {
            $method = 'build' . ucfirst($command->name) . 'Command';
            if (method_exists($this, $method)) {
                $statements[] = $this->$method($command->parameters);
            }
        }
        return implode(";\n", $statements);
    }

    protected function buildDropColumnCommand(array $parameters): string
    {
        $columns = array_map(fn($c) => "DROP COLUMN `{$c}`", $parameters['columns']);
        return "ALTER TABLE `{$this->table}` " . implode(', ', $columns);
    }

    protected function buildRenameColumnCommand(array $parameters): string
    {
        return "ALTER TABLE `{$this->table}` CHANGE COLUMN `{$parameters['from']}` `{$parameters['to']}`";
    }

    protected function buildForeignCommand(array $parameters): string
    {
        $definition = $parameters['definition'];
        $columns = '`' . implode('`, `', $definition->columns) . '`';
        $foreignKeyName = $this->createIndexName('foreign', $definition->columns);

        $sql = "ALTER TABLE `{$this->table}` ADD CONSTRAINT `{$foreignKeyName}` FOREIGN KEY ({$columns}) REFERENCES `{$definition->on}` (`{$definition->references}`)";

        if ($definition->onDelete) {
            $sql .= " ON DELETE " . strtoupper($definition->onDelete);
        }
        if ($definition->onUpdate) {
            $sql .= " ON UPDATE " . strtoupper($definition->onUpdate);
        }
        return $sql;
    }
    protected function buildColumnDefinition(ColumnDefinition $column): string
    {
        $sql = "`{$column->name}` " . strtoupper($column->type);

        if ($column->type === 'enum' && !empty($column->values)) {
            $sql .= "('" . implode("','", $column->values) . "')";
        } elseif ($column->type === 'decimal' && isset($column->precision, $column->scale)) {
            $sql .= "({$column->precision}, {$column->scale})";
        } elseif (isset($column->length)) {
            $sql .= "({$column->length})";
        }

        if ($column->unsigned) {
            $sql .= ' UNSIGNED';
        }

        if ($column->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        if ($column->nullable) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        if (isset($column->default)) {
            $default = is_string($column->default) && !in_array(strtoupper($column->default), ['CURRENT_TIMESTAMP'])
                ? "'{$column->default}'"
                : $column->default;
            $sql .= " DEFAULT {$default}";
        }

        if (isset($column->onUpdate)) {
            $sql .= " ON UPDATE {$column->onUpdate}";
        }

        return $sql;
    }

    protected function buildCommandDefinition(object $command): ?string
    {
        $columns = '`' . implode('`, `', $command->parameters['columns']) . '`';

        switch ($command->name) {
            case 'primary':
                return "PRIMARY KEY ({$columns})";
            case 'unique':
                $indexName = $this->createIndexName('unique', $command->parameters['columns']);
                return "UNIQUE KEY `{$indexName}` ({$columns})";
            case 'index':
                $indexName = $this->createIndexName('index', $command->parameters['columns']);
                return "INDEX `{$indexName}` ({$columns})";
            case 'foreign':
                $definition = $command->parameters['definition'];
                $foreignKeyName = $this->createIndexName('foreign', $definition->columns);
                $sql = "CONSTRAINT `{$foreignKeyName}` FOREIGN KEY ({$columns}) REFERENCES `{$definition->on}` (`{$definition->references}`)";

                if ($definition->onDelete) {
                    $sql .= " ON DELETE " . strtoupper($definition->onDelete);
                }
                if ($definition->onUpdate) {
                    $sql .= " ON UPDATE " . strtoupper($definition->onUpdate);
                }
                return $sql;
        }
        return null;
    }

    protected function createIndexName(string $type, array $columns): string
    {
        $index = strtolower($this->table . '_' . implode('_', $columns) . '_' . $type);
        return str_replace(['-', '.'], '_', $index);
    }
    
}
