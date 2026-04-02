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
        'stage',
        'title',
        'percentage',
        'amount',
        'status',
        'evidence_urls',
        'evidence_description',
        'evidence_submitted_at',
        'approved_at',
        'released_at',
        'contested_at',
        'contest_reason',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'evidence_urls' => 'array',
        'evidence_submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'released_at' => 'datetime',
        'contested_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isReleased(): bool
    {
        return $this->status === 'released';
    }

    public function isDisputed(): bool
    {
        return $this->status === 'disputed';
    }

    public function hasEvidence(): bool
    {
        return !empty($this->evidence_urls) && $this->evidence_submitted_at !== null;
    }
}
