<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ispconfig_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blueprint_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prompt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('php_version_id')
                  ->nullable()
                  ->constrained('ispconfig_php_versions')
                  ->nullOnDelete();

            // Dati sito
            $table->string('domain')->unique();
            $table->string('site_name');
            $table->text('description')->nullable();
            $table->string('locale')->default('en_US');
            $table->string('docroot')->nullable();

            // Dati ISPConfig (popolati dai job)
            $table->integer('ispconfig_domain_id')->nullable();
            $table->integer('ispconfig_db_id')->nullable();

            // Dati database WordPress (encrypted)
            $table->string('db_name')->nullable();
            $table->string('db_user')->nullable();
            $table->text('db_password')->nullable();         // encrypted

            // Dati admin WordPress
            $table->string('wp_admin_email');
            $table->text('wp_admin_password')->nullable();   // encrypted, one-time

            // Logo
            $table->string('logo_url')->nullable();
            $table->timestamp('logo_generated_at')->nullable();

            // Stato provisioning
            $table->enum('status', [
                'pending',
                'provisioning',
                'active',
                'disabled',
                'error',
            ])->default('pending');
            $table->string('current_step')->nullable();
            $table->boolean('ssl_enabled')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
