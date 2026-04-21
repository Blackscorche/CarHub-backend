<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class SupplierInsuranceTag extends Model
{
    use HasUuids;

    protected $fillable = ['supplier_id', 'insurance_name'];

    protected static function booted(): void
    {
        $invalidate = fn (SupplierInsuranceTag $t) => Cache::forget("supplier:profile:{$t->supplier_id}");
        static::saved($invalidate);
        static::deleted($invalidate);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
