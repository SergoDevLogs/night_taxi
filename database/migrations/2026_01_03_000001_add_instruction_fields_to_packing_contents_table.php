<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packing_contents', function (Blueprint $table) {
            $table->string('position_hint')->nullable()->after('z');
            $table->text('step_text')->nullable()->after('position_hint');
            $table->json('supported_by')->nullable()->after('step_text');
            $table->json('neighbors')->nullable()->after('supported_by');
            $table->float('support_ratio')->default(1.0)->after('neighbors');
            $table->string('stability_warning')->nullable()->after('support_ratio');
        });
    }

    public function down(): void
    {
        Schema::table('packing_contents', function (Blueprint $table) {
            $table->dropColumn([
                'position_hint',
                'step_text',
                'supported_by',
                'neighbors',
                'support_ratio',
                'stability_warning',
            ]);
        });
    }
};
