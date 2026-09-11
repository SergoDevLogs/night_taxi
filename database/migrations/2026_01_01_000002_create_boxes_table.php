<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL 12+ поддерживает generated columns
        DB::statement(<<<'SQL'
            CREATE TABLE boxes (
                id            BIGSERIAL PRIMARY KEY,
                name          VARCHAR(255) NOT NULL,
                inner_height  INTEGER NOT NULL,
                inner_length  INTEGER NOT NULL,
                inner_width   INTEGER NOT NULL,
                max_weight    INTEGER NOT NULL,
                volume        INTEGER GENERATED ALWAYS AS (inner_height * inner_length * inner_width) STORED,
                biggest_side  INTEGER GENERATED ALWAYS AS (GREATEST(inner_height, inner_length, inner_width)) STORED,
                created_at    TIMESTAMP NULL,
                updated_at    TIMESTAMP NULL,
                CHECK (inner_height > 0 AND inner_length > 0 AND inner_width > 0 AND max_weight > 0)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('boxes');
    }
};
