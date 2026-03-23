<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_id', 'order_id', 'customer_id',
        'scheduled_date', 'scheduled_time', 'duration_minutes',
        'status', 'reminder_sent',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'reminder_sent' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
