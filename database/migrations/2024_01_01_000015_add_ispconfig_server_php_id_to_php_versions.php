<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ispconfig_php_versions', function (Blueprint $table) {
            $table->unsignedInteger('ispconfig_server_php_id')->nullable()->after('fpm_config_path');
        });
    }

    public function down(): void
    {
        Schema::table('ispconfig_php_versions', function (Blueprint $table) {
            $table->dropColumn('ispconfig_server_php_id');
        });
    }
};
