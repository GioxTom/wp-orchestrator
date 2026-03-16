<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blueprints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('zip_path')->nullable();         // path in storage/app/blueprints/
            $table->json('plugin_list')->nullable();         // [{slug, version, activate}]
            $table->json('wp_settings')->nullable();         // {permalink, timezone, ...}
            $table->text('child_skeleton')->nullable();      // template functions.php child theme
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->string('version')->default('1.0.0');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprints');
    }
};
