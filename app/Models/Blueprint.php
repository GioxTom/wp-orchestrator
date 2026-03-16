<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Blueprint extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'zip_path',
        'plugin_list', 'wp_settings', 'child_skeleton',
        'status', 'version',
    ];

    protected $casts = [
        'plugin_list' => 'array',
        'wp_settings' => 'array',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
