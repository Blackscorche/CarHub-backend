<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class CatalogItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_id', 'type', 'name', 'description', 'price',
        'price_type', 'estimated_duration_minutes', 'category',
        'image_urls', 'is_active', 'stock_quantity', 'requires_scheduling',
    ];

    protected static function booted(): void
    {
        $invalidate = fn (CatalogItem $c) => Cache::forget("supplier:profile:{$c->supplier_id}");
        static::saved($invalidate);
        static::deleted($invalidate);
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'image_urls' => 'array',
            'is_active' => 'boolean',
            'requires_scheduling' => 'boolean',
        ];
    }

    public function getImageUrlsAttribute($value): array
    {
        $urls = is_string($value) ? json_decode($value, true) : ($value ?? []);
        return array_map(function ($url) {
            if (str_starts_with($url, 'http')) {
                return $url;
            }
            return url($url);
        }, $urls);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
