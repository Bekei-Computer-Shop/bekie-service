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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('username')->unique()->nullable();

            // Authentication
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();

            $table->string('password');

            // Profile
            $table->string('avatar')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();

            // Role & Status
            $table->string('role')->default('user');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_banned')->default(false);

            // Security
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            // Soft Delete
            $table->softDeletes();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['email', 'phone']);
            $table->index('role');
            $table->index('is_active');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->foreignId('user_id')
                ->nullable()
                ->index()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->longText('payload');

            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};