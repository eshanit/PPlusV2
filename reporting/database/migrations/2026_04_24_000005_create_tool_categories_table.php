<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tool_id');
            $table->string('name', 200);
            $table->smallInteger('sort_order')->default(0);

            $table->foreign('tool_id')->references('id')->on('tools');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_categories');
    }
};
