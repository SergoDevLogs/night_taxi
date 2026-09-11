<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name');
            $table->integer('height');      // мм
            $table->integer('length');      // мм
            $table->integer('width');       // мм
            $table->integer('weight');      // г
            $table->boolean('can_rotate')->default(true);
            $table->boolean('fragile')->default(false);
            $table->boolean('top_bottom')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
