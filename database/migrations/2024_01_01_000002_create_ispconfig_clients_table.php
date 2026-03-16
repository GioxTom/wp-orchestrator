<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ispconfig_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->integer('ispconfig_client_id');
            $table->string('company_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'ispconfig_client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ispconfig_clients');
    }
};
