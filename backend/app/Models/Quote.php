<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quote extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id', 'supplier_id', 'customer_id', 'description',
        'media_urls', 'initial_price', 'final_price',
        'estimated_duration', 'supplier_notes',
        'partial_payment_percent', 'status', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'media_urls' => 'array',
            'initial_price' => 'decimal:2',
            'final_price' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
