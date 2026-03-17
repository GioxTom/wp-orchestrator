<?php

namespace App\Models;

use App\Contracts\ServerConnection;
use App\Services\Connections\LocalConnection;
use App\Services\Connections\SshConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hostname',
        'ip',
        'connection_type',
        'ssh_user',
        'ssh_key_path',
        'ispconfig_api_url',
        'ispconfig_user',
        'ispconfig_password',
        'status',
        'default_php_version_id',
        'notes',
    ];

    protected $casts = [
        'ispconfig_password' => 'encrypted',
    ];

    protected $hidden = [
        'ispconfig_password',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function ispConfigClients(): HasMany
    {
        return $this->hasMany(IspConfigClient::class);
    }

    public function phpVersions(): HasMany
    {
        return $this->hasMany(IspConfigPhpVersion::class);
    }

    public function defaultPhpVersion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(IspConfigPhpVersion::class, 'default_php_version_id');
    }

    /**
     * Restituisce la connessione corretta (local o SSH) in base alla configurazione.
     * I Job non devono sapere come è connesso il server — usano solo questa interfaccia.
     */
    public function connection(): ServerConnection
    {
        return match ($this->connection_type) {
            'ssh'   => new SshConnection($this),
            default => new LocalConnection(),
        };
    }
}
