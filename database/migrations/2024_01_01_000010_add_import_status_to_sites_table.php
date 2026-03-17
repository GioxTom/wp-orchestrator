<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // Aggiunge import_blueprint_pending all'enum status
            \DB::statement("ALTER TABLE sites MODIFY COLUMN status ENUM(
                'pending',
                'provisioning',
                'active',
                'disabled',
                'error',
                'import_blueprint_pending'
            ) NOT NULL DEFAULT 'pending'");
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            \DB::statement("ALTER TABLE sites MODIFY COLUMN status ENUM(
                'pending',
                'provisioning',
                'active',
                'disabled',
                'error'
            ) NOT NULL DEFAULT 'pending'");
        });
    }
};
