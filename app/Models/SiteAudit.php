<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAudit extends Model
{
    protected $fillable = [
        'site_id', 'http_status', 'https_status',
        'cert_expiry_at', 'admin_ok', 'mu_plugin_ok', 'checked_at',
    ];

    protected $casts = [
        'cert_expiry_at' => 'datetime',
        'checked_at'     => 'datetime',
        'admin_ok'       => 'boolean',
        'mu_plugin_ok'   => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isHealthy(): bool
    {
        return $this->http_status === 200
            && $this->https_status === 200
            && $this->admin_ok
            && $this->mu_plugin_ok;
    }

    public function isSslExpiringSoon(): bool
    {
        return $this->cert_expiry_at
            && $this->cert_expiry_at->diffInDays(now()) <= 14;
    }
}
