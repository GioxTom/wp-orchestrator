<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'ispconfig_client_id',
        'blueprint_id',
        'prompt_id',
        'php_version_id',
        'domain',
        'site_name',
        'description',
        'locale',
        'docroot',
        'ispconfig_domain_id',
        'ispconfig_db_id',
        'db_name',
        'db_user',
        'db_password',
        'wp_admin_email',
        'wp_admin_password',
        'logo_url',
        'logo_generated_at',
        'logo_batch_job',
        'logo_status',
        'status',
        'current_step',
        'ssl_enabled',
    ];

    protected $casts = [
        'db_password'        => 'encrypted',
        'wp_admin_password'  => 'encrypted',
        'ssl_enabled'        => 'boolean',
        'logo_generated_at'  => 'datetime',
    ];

    protected $hidden = [
        'db_password',
        'wp_admin_password',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function ispConfigClient(): BelongsTo
    {
        return $this->belongsTo(IspConfigClient::class);
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function phpVersion(): BelongsTo
    {
        return $this->belongsTo(IspConfigPhpVersion::class, 'php_version_id');
    }

    public function provisioningLogs(): HasMany
    {
        return $this->hasMany(ProvisioningLog::class)->orderBy('id');
    }

    public function latestAudit(): HasOne
    {
        return $this->hasOne(SiteAudit::class)->latestOfMany();
    }

    public function audits(): HasMany
    {
        return $this->hasMany(SiteAudit::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isProvisioning(): bool
    {
        return $this->status === 'provisioning';
    }

    /**
     * Restituisce il prompt risolto con i placeholder sostituiti.
     */
    public function resolvedPrompt(): ?string
    {
        if (! $this->prompt) {
            return null;
        }

        return str_replace(
            ['{site_name}', '{site_description}', '{locale}'],
            [$this->site_name, $this->description, $this->locale],
            $this->prompt->content
        );
    }
}
