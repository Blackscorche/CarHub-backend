<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Review extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id', 'customer_id', 'supplier_id',
        'rating', 'comment', 'media_urls',
    ];

    protected static function booted(): void
    {
        $invalidate = fn (Review $r) => Cache::forget("supplier:profile:{$r->supplier_id}");
        static::saved($invalidate);
        static::deleted($invalidate);
    }

    protected function casts(): array
    {
        return [
            'media_urls' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
