<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('pm')->nullable()->after('ssl_enabled');
            $table->unsignedSmallInteger('pm_max_children')->nullable()->after('pm');
            $table->unsignedSmallInteger('pm_process_idle_timeout')->nullable()->after('pm_max_children');
            $table->unsignedSmallInteger('pm_max_requests')->nullable()->after('pm_process_idle_timeout');
            $table->integer('hd_quota')->nullable()->after('pm_max_requests');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['pm', 'pm_max_children', 'pm_process_idle_timeout', 'pm_max_requests', 'hd_quota']);
        });
    }
};
