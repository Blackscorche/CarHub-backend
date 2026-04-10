<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlatformConfig extends Model
{
    use HasUuids;

    protected $fillable = ['key', 'value', 'description'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $config = static::where('key', $key)->first();
        if (!$config) return $default;
        return $config->value['default'] ?? $config->value ?? $default;
    }
}
