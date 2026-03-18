<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // Categorie
            $table->boolean('auto_categories')->default(true)->after('logo_aspect_ratio');
            $table->unsignedTinyInteger('categories_count')->default(4)->after('auto_categories');
            $table->string('categories_ai_provider')->nullable()->after('categories_count'); // null = usa default globale
            $table->string('categories_ai_model')->nullable()->after('categories_ai_provider');
            $table->foreignId('categories_prompt_id')->nullable()->after('categories_ai_model')->constrained('prompts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropForeign(['categories_prompt_id']);
            $table->dropColumn([
                'auto_categories',
                'categories_count',
                'categories_ai_provider',
                'categories_ai_model',
                'categories_prompt_id',
            ]);
        });
    }
};
