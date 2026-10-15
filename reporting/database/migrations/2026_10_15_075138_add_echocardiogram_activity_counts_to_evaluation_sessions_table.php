<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('evaluation_sessions', function (Blueprint $table) {
            $table->unsignedInteger('echocardiograms_performed')->nullable();
            $table->unsignedInteger('echocardiograms_correct_diagnosis')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'echocardiograms_performed',
                'echocardiograms_correct_diagnosis',
            ]);
        });
    }
};
