<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CouchDB-synced clinician records (evaluators + mentees).
        // email/password/remember_token allow a subset of users to log into the reporting dashboard.
        // facility_id/district_id FKs are added after those tables are created.
        Schema::create('users', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('firstname', 100);
            $table->string('lastname', 100);
            $table->string('username', 100)->nullable()->unique();
            $table->string('profession', 100)->nullable();
            $table->char('facility_id', 36)->nullable();
            $table->char('district_id', 36)->nullable();
            $table->string('couchdb_rev', 100)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            // Optional auth columns for reporting dashboard access
            $table->string('email')->nullable()->unique();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->char('user_id', 36)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
