<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hostname');
            $table->string('ip');
            $table->enum('connection_type', ['local', 'ssh'])->default('local');
            $table->string('ssh_user')->nullable();
            $table->string('ssh_key_path')->nullable();
            $table->string('ispconfig_api_url');
            $table->string('ispconfig_user');
            $table->text('ispconfig_password'); // encrypted
            $table->enum('status', ['active', 'inactive', 'error'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
