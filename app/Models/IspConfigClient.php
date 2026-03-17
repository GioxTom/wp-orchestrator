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
        'contact_name', 'email', 'username', 'synced_at', 'is_default',
    ];

    protected $casts = [
        'synced_at'  => 'datetime',
        'is_default' => 'boolean',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class, 'ispconfig_client_id');
    }

    public function getDisplayNameAttribute(): string
    {
        $default = $this->is_default ? ' ⭐' : '';
        return ($this->company_name ?? $this->contact_name ?? $this->email ?? "Client #{$this->ispconfig_client_id}") . $default;
    }

    /**
     * Imposta questo cliente come default e rimuove il default dagli altri.
     */
    public function setAsDefault(): void
    {
        // Rimuove il default da tutti gli altri clienti dello stesso server
        static::where('server_id', $this->server_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
}

    /**
     * Restituisce il cliente default per un server.
     */
    public static function getDefault(int $serverId): ?static
    {
        return static::where('server_id', $serverId)
            ->where('is_default', true)
            ->first();
    }
}
