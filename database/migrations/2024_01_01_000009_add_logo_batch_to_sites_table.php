<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('logo_batch_job')->nullable()->after('logo_generated_at');
            $table->enum('logo_status', [
                'none',          // nessuna generazione richiesta o completata
                'pending',       // in coda (sincrono o batch appena inviato)
                'batch_pending', // batch inviato, in attesa risposta Gemini
                'done',          // logo generato e applicato
                'failed',        // generazione fallita
            ])->default('none')->after('logo_batch_job');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['logo_batch_job', 'logo_status']);
        });
    }
};
