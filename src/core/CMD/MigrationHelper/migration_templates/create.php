<?php

namespace Pluto\Core\CMD\MigrationHelper;
//migration_status=created
use Pluto\Core\CMD\MigrationHelper\Schema;
use Pluto\Core\CMD\MigrationHelper\Table;

return new class
{
    public function up()
    {
        Schema::create('test', function (Table $table) {
            $table->id();
            $table->string('name')->nullable();
        });
    }

    public function down()
    {
        Schema::drop('test');
    }
};