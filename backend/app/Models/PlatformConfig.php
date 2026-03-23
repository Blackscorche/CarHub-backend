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
}
