<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('box_id')->constrained('boxes');
            $table->unsignedInteger('order_index');
            $table->timestamps();

            $table->unique(['order_id', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_boxes');
    }
};
