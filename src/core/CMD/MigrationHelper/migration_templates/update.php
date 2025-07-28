<?php

namespace Pluto\Core\CMD\MigrationHelper;
//migration_status=created
use Pluto\Core\CMD\MigrationHelper\Schema;
use Pluto\Core\CMD\MigrationHelper\Table;

return new class
{
    public function up()
    {
        Schema::update('test', function (Table $table) {
            $table->string('updated_column')->nullable();
        });
    }

    public function down()
    {
        Schema::update('test', function (Table $table) {
            
        });
    }
};