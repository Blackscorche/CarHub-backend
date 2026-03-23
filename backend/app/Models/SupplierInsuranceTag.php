<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInsuranceTag extends Model
{
    use HasUuids;

    protected $fillable = ['supplier_id', 'insurance_name'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
