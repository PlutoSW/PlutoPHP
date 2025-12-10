<?php

namespace Pluto\Migrate;

class ColumnDefinition
{
    public string $type;
    public string $name;
    public ?int $length = null;
    public bool $nullable = false;
    public $default = null;
    public bool $unsigned = false;
    public bool $autoIncrement = false;
    public bool $primary = false;
    public bool $unique = false;
    public ?string $onUpdate = null;
    public bool $change = false;

    public function __construct(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    public function default($value): self
    {
        $this->default = $value;
        return $this;
    }

    public function unsigned(bool $value = true): self
    {
        $this->unsigned = $value;
        return $this;
    }

    public function autoIncrement(bool $value = true): self
    {
        $this->autoIncrement = $value;
        return $this;
    }

    public function primary(bool $value = true): self
    {
        $this->primary = $value;
        return $this;
    }

    public function onUpdate(string $value): self
    {
        $this->onUpdate = $value;
        return $this;
    }

    public function unique(bool $value = true): self
    {
        $this->unique = $value;
        return $this;
    }

    public function change(bool $value = true): self
    {
        $this->change = $value;
        return $this;
    }
}