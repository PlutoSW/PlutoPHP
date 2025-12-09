<?php

namespace Pluto\Migrate;

interface Migration
{
    public function up(): void;
    public function down(): void;
}
