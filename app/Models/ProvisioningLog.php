<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisioningLog extends Model
{
    protected $fillable = [
        'site_id', 'job_class', 'step_label',
        'status', 'output', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function markRunning(): void
    {
        $this->update(['status' => 'running', 'started_at' => now()]);
    }

    public function markSuccess(string $output = ''): void
    {
        $this->update([
            'status'      => 'success',
            'output'      => $output,
            'finished_at' => now(),
        ]);
    }

    public function markFailed(string $output = ''): void
    {
        $this->update([
            'status'      => 'failed',
            'output'      => $output,
            'finished_at' => now(),
        ]);
    }
}
