<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('action');                        // logo_generation | content_generation | ...
            $table->enum('type', ['system', 'user'])->default('user');
            $table->text('content');                         // testo prompt con placeholder {site_name} {site_description} {locale}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompts');
    }
};
