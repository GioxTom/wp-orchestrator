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
        'www_mode',
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
        'logo_aspect_ratio',
        'auto_categories',
        'categories_count',
        'categories_ai_provider',
        'categories_ai_model',
        'categories_prompt_id',
        'status',
        'current_step',
        'ssl_enabled',
        // PHP-FPM overrides (null = usa default server)
        'pm',
        'pm_max_children',
        'pm_process_idle_timeout',
        'pm_max_requests',
        'hd_quota',
    ];

    protected $casts = [
        'db_password'             => 'encrypted',
        'wp_admin_password'       => 'encrypted',
        'ssl_enabled'             => 'boolean',
        'logo_generated_at'       => 'datetime',
        'auto_categories'         => 'boolean',
        'categories_count'        => 'integer',
        'pm_max_children'         => 'integer',
        'pm_process_idle_timeout' => 'integer',
        'pm_max_requests'         => 'integer',
        'hd_quota'                => 'integer',
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
        return $this->belongsTo(IspConfigClient::class, 'ispconfig_client_id');
    }

    public function categoriesPrompt(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Prompt::class, 'categories_prompt_id');
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

    // ── Valori PHP-FPM effettivi: override sito oppure default server ──────────

    public function effectivePm(): string
    {
        return $this->pm ?? $this->server->default_pm ?? 'ondemand';
    }

    public function effectivePmMaxChildren(): int
    {
        return $this->pm_max_children ?? $this->server->default_pm_max_children ?? 10;
    }

    public function effectivePmProcessIdleTimeout(): int
    {
        return $this->pm_process_idle_timeout ?? $this->server->default_pm_process_idle_timeout ?? 10;
    }

    public function effectivePmMaxRequests(): int
    {
        return $this->pm_max_requests ?? $this->server->default_pm_max_requests ?? 0;
    }

    public function effectiveHdQuota(): int
    {
        return $this->hd_quota ?? $this->server->default_hd_quota ?? -1;
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
