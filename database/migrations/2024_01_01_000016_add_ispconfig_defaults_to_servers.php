<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedSmallInteger('apache_http_port')->default(8082)->after('default_php_version_id');
            $table->unsignedSmallInteger('apache_https_port')->default(8082)->after('apache_http_port');
            $table->string('default_pm')->default('ondemand')->after('apache_https_port');
            $table->unsignedSmallInteger('default_pm_max_children')->default(10)->after('default_pm');
            $table->unsignedSmallInteger('default_pm_process_idle_timeout')->default(10)->after('default_pm_max_children');
            $table->unsignedSmallInteger('default_pm_max_requests')->default(0)->after('default_pm_process_idle_timeout');
            $table->integer('default_hd_quota')->default(-1)->after('default_pm_max_requests');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'apache_http_port', 'apache_https_port',
                'default_pm', 'default_pm_max_children',
                'default_pm_process_idle_timeout', 'default_pm_max_requests',
                'default_hd_quota',
            ]);
        });
    }
};
