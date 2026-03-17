<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->foreignId('default_php_version_id')
                ->nullable()
                ->after('status')
                ->constrained('ispconfig_php_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\IspConfigPhpVersion::class, 'default_php_version_id');
            $table->dropColumn('default_php_version_id');
        });
    }
};
