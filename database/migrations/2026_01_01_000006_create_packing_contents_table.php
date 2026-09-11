<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packing_box_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('position');
            $table->string('orientation', 32);
            $table->integer('x')->nullable();
            $table->integer('y')->nullable();
            $table->integer('z')->nullable();
            $table->timestamps();

            $table->unique(['packing_box_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_contents');
    }
};
