<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMilestone extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'sequence',
        'amount',
        'percentage',
        'pagarme_charge_id',
        'status',
        'is_final',
        'evidence_urls',
        'evidence_description',
        'paid_at',
        'delivered_at',
        'approved_at',
        'declined_at',
        'released_at',
        'contested_at',
        'contest_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'is_final' => 'boolean',
        'evidence_urls' => 'array',
        'paid_at' => 'datetime',
        'delivered_at' => 'datetime',
        'approved_at' => 'datetime',
        'declined_at' => 'datetime',
        'released_at' => 'datetime',
        'contested_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    public function isContested(): bool
    {
        return $this->status === 'contested';
    }

    public function hasEvidence(): bool
    {
        return !empty($this->evidence_urls) && $this->delivered_at !== null;
    }

    public function isInContestationWindow(): bool
    {
        if (!$this->is_final || !$this->approved_at) {
            return false;
        }

        return now()->diffInHours($this->approved_at) < 48;
    }
}
