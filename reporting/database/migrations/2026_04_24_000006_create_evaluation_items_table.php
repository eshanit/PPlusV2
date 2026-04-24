<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tool_id');
            $table->unsignedInteger('category_id')->nullable();
            $table->string('slug', 100)->unique();
            $table->string('number', 20);
            $table->text('title');
            $table->boolean('is_advanced')->default(false);
            $table->smallInteger('sort_order')->default(0);

            $table->foreign('tool_id')->references('id')->on('tools');
            $table->foreign('category_id')->references('id')->on('tool_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_items');
    }
};
