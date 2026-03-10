<?php

namespace Pluto\Migrate;

class ForeignKeyDefinition
{
    public array $columns;
    public ?string $name = null;
    public ?string $references = null;
    public ?string $on = null;
    public ?string $onDelete = null;
    public ?string $onUpdate = null;

    public function __construct(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public function references(string $column): self
    {
        $this->references = $column;
        return $this;
    }

    public function on(string $table): self
    {
        $this->on = $table;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = $action;
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;
        return $this;
    }
}