<?php

namespace Pluto\Core\CMD\MigrationGenerator;

class Table
{
    private $schema;
    private $table;
    private $sqlLines = [];
    private $engine = 'InnoDB';
    private $charset = 'utf8mb4';
    private $collation = 'utf8mb4_unicode_ci';

    private $comment;
    private $type;


    public function __construct($schema, $type = "create")
    {
        $this->schema = $schema;
        $this->table = $schema::$table;
        $this->type = $type;
        if ($type === "create") {
            $this->schema::$sqlString = "CREATE TABLE IF NOT EXISTS `$this->table` ( " . PHP_EOL . "  ";
        } elseif ($type === "update") {
            $this->schema::$sqlString = "ALTER TABLE `$this->table` ";
        } elseif ($type === "delete") {
            $this->schema::$sqlString = "DROP TABLE IF EXISTS `$this->table`;" . PHP_EOL;
        } else {
            throw new \InvalidArgumentException("Invalid type provided. Use 'create', 'update', or 'delete'.");
        }
    }

    public function engine($engine = 'InnoDB'): self
    {
        $this->engine = $engine;
        return $this;
    }

    public function charset($charset = 'utf8mb4'): self
    {
        $this->charset = $charset;
        return $this;
    }

    public function collation($collation = 'utf8mb4_unicode_ci'): self
    {
        $this->collation = $collation;
        return $this;
    }

    public function comment($comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function id(): Line
    {
        $line = new Line('id', 'INT AUTO_INCREMENT PRIMARY KEY');
        $this->sqlLines[] = $line;
        return $line;
    }

    public function string($name, $length = 255): Line
    {
        $line = new Line($name, "VARCHAR($length)");
        $this->sqlLines[] = $line;
        return $line;
    }

    public function text($name): Line
    {
        $line = new Line($name, 'TEXT');
        $this->sqlLines[] = $line;
        return $line;
    }

    public function integer($name): Line
    {
        $line = new Line($name, 'INT');
        $this->sqlLines[] = $line;
        return $line;
    }

    public function float($name): Line
    {
        $line = new Line($name, 'FLOAT');
        $this->sqlLines[] = $line;
        return $line;
    }

    public function boolean($name): Line
    {
        $line = new Line($name, 'BOOLEAN');
        $this->sqlLines[] = $line;
        return $line;
    }

    public function date($name): Line
    {
        $line = new Line($name, 'DATE');
        $this->sqlLines[] = $line;
        return $line;
    }

    public function datetime($name): Line
    {
        $line = new Line($name, 'DATETIME');
        $this->sqlLines[] = $line;
        return $line;
    }

    public function timestamps(): void
    {
        $this->sqlLines[] = new Line('created_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->sqlLines[] = new Line('updated_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }

    public function json($name): Line
    {
        $line = new Line($name, 'JSON');
        $this->sqlLines[] = $line;
        return $line;
    }

    public function custom($name, $type): Line
    {
        $line = new Line($name, $type);
        $this->sqlLines[] = $line;
        return $line;
    }

    public function __destruct()
    {
        $this->schema::$sqlString .= implode("," . PHP_EOL . "  ", $this->sqlLines) . "\n";

        if ($this->type === "create") {
            $this->schema::$sqlString .= ") ENGINE=$this->engine DEFAULT CHARSET=$this->charset COLLATE=$this->collation";
            if ($this->comment) {
                $this->schema::$sqlString .= " COMMENT='{$this->comment}'";
            }
            $this->schema::$sqlString .= ";" . PHP_EOL . \PHP_EOL;
        } elseif ($this->type === "update") {
            $this->schema::$sqlString .= ";";
        } elseif ($this->type === "delete") {
            // No additional SQL needed for delete
        }
    }
}


class Line
{
    private $name;
    private $type;
    private $options;

    public function __construct($name, $type, $options = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->options = $options;
    }

    public function nullable()
    {
        $this->options[] = 'NULL';
        return $this;
    }

    public function default($value)
    {
        $this->options[] = "DEFAULT '{$value}'";
        return $this;
    }

    public function unique()
    {
        $this->options[] = 'UNIQUE';
        return $this;
    }

    public function unsigned()
    {
        $this->options[] = 'UNSIGNED';
        return $this;
    }

    public function autoIncrement()
    {
        $this->options[] = 'AUTO_INCREMENT';
        return $this;
    }

    public function after($column)
    {
        $this->options[] = "AFTER `$column`";
        return $this;
    }

    public function before($column)
    {
        $this->options[] = "BEFORE `$column`";
        return $this;
    }

    public function primaryKey()
    {
        $this->options[] = 'PRIMARY KEY';
        return $this;
    }


    public function primary()
    {
        $this->options[] = 'PRIMARY KEY';
        return $this;
    }

    public function index()
    {
        $this->options[] = 'INDEX';
        return $this;
    }

    public function comment($comment)
    {
        $this->options[] = "COMMENT '{$comment}'";
        return $this;
    }

    public function foreignKey($referenceTable, $referenceColumn = 'id')
    {
        $this->options[] = "FOREIGN KEY REFERENCES `$referenceTable`(`$referenceColumn`)";
        return $this;
    }

    public function foreign($referenceTable, $referenceColumn = 'id')
    {
        $this->options[] = "FOREIGN KEY REFERENCES `$referenceTable`(`$referenceColumn`)";
        return $this;
    }

    public function onDelete($action)
    {
        $this->options[] = "ON DELETE $action";
        return $this;
    }

    public function onUpdate($action)
    {
        $this->options[] = "ON UPDATE $action";
        return $this;
    }

    public function __toString(): string
    {
        return "`{$this->name}` {$this->type} " . implode(' ', $this->options);
    }
}
