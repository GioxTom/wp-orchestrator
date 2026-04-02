<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IspConfigPhpVersion extends Model
{
    protected $table = 'ispconfig_php_versions'; // ← aggiungi questa riga

    protected $fillable = [
        'server_id', 'label', 'version', 'fpm_config_path', 'ispconfig_server_php_id',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
