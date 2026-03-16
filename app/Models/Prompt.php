<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prompt extends Model
{
    protected $fillable = [
        'name', 'slug', 'action', 'type', 'content',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function isSystem(): bool
    {
        return $this->type === 'system';
    }

    public function isDeletable(): bool
    {
        return $this->type !== 'system';
    }

    public function isEditable(): bool
    {
        return $this->type !== 'system';
    }

    /**
     * Duplica il prompt come tipo 'user' per override personalizzato.
     */
    public function duplicate(string $newName): static
    {
        return static::create([
            'name'    => $newName,
            'slug'    => \Str::slug($newName) . '-' . uniqid(),
            'action'  => $this->action,
            'type'    => 'user',
            'content' => $this->content,
        ]);
    }
}
