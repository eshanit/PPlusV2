<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('district_id', 36);
            $table->string('name');
            $table->string('couchdb_rev', 100)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('district_id')->references('id')->on('districts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
