<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaim extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id', 'customer_id', 'supplier_id',
        'protocol_number', 'insurance_name', 'policy_number',
        'claim_type', 'description', 'evidence_urls',
        'vehicle_info', 'protocol_data', 'status',
        'insurer_reference', 'insurer_response',
        'sent_at', 'responded_at', 'completed_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'evidence_urls' => 'array',
            'vehicle_info' => 'array',
            'protocol_data' => 'array',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
            'completed_at' => 'datetime',
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
