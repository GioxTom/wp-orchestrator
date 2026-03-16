<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IspConfigClient extends Model
{
    protected $table = 'ispconfig_clients'; // ← aggiungi questa riga

        
    protected $fillable = [
        'server_id', 'ispconfig_client_id', 'company_name',
        'contact_name', 'email', 'username', 'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->company_name ?? $this->contact_name ?? $this->email ?? "Client #{$this->ispconfig_client_id}";
    }
}
