<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'is_encrypted'];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Recupera il valore di una chiave, decifrando se necessario.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return $setting->is_encrypted
            ? Crypt::decryptString($setting->value)
            : $setting->value;
    }

    /**
     * Salva il valore di una chiave, cifrando se richiesto.
     */
    public static function set(string $key, mixed $value, bool $encrypt = false): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value'        => $encrypt ? Crypt::encryptString($value) : $value,
                'is_encrypted' => $encrypt,
            ]
        );
    }
}
