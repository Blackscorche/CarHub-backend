<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_number', 'customer_id', 'supplier_id', 'vehicle_id',
        'type', 'payment_model', 'status', 'subtotal', 'platform_fee', 'total',
        'partial_payment_amount', 'remaining_amount', 'commission_rate',
        'payment_method', 'delivery_type', 'delivery_address_id',
        'confirmation_code', 'notes', 'invoice_url', 'scheduled_at',
        'accepted_at', 'started_at', 'completed_at',
        'cancelled_at', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'partial_payment_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'scheduled_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function quote(): HasOne
    {
        return $this->hasOne(Quote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(PaymentMilestone::class)->orderBy('sequence');
    }

    public function isMilestone(): bool
    {
        return $this->payment_model === 'milestone';
    }

    public function isInstant(): bool
    {
        return $this->payment_model === 'instant';
    }
}
