<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBankAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_id',
        'bank_code',
        'agencia',
        'agencia_dv',
        'conta',
        'conta_dv',
        'type',
        'document_type',
        'document_number',
        'legal_name',
        'pagarme_bank_account_id',
    ];

    protected $hidden = [
        'conta',
        'conta_dv',
        'document_number',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
