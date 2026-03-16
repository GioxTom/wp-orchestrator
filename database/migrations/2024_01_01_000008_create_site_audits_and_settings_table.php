<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->integer('http_status')->nullable();
            $table->integer('https_status')->nullable();
            $table->timestamp('cert_expiry_at')->nullable();
            $table->boolean('admin_ok')->default(false);
            $table->boolean('mu_plugin_ok')->default(false);
            $table->timestamp('checked_at');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();               // encrypted per dati sensibili
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_audits');
        Schema::dropIfExists('settings');
    }
};
