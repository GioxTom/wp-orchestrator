<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->json('pages')->nullable()->after('wp_settings');
            $table->json('menus')->nullable()->after('pages');
            $table->json('widgets')->nullable()->after('menus');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->enum('www_mode', ['www', 'no-www'])->default('www')->after('domain');
        });
    }

    public function down(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropColumn(['pages', 'menus', 'widgets']);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('www_mode');
        });
    }
};
