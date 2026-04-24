<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_checkpoints', function (Blueprint $table) {
            $table->string('db_name', 100)->primary();
            $table->string('last_seq', 200);
            $table->timestamp('last_synced_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_checkpoints');
    }
};
