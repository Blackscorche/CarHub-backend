<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Supplier extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::saved(fn (Supplier $s) => Cache::forget("supplier:profile:{$s->id}"));
        static::deleted(fn (Supplier $s) => Cache::forget("supplier:profile:{$s->id}"));
    }

    protected $fillable = [
        'user_id', 'business_name', 'cnpj', 'description',
        'cover_image_url', 'logo_url', 'category', 'categories',
        'service_radius_km', 'address_id', 'latitude', 'longitude',
        'opening_hours', 'avg_rating', 'total_ratings',
        'is_verified', 'kyc_document_url', 'approval_status',
        'approved_at', 'rejection_reason', 'rejected_at',
        'pagarme_recipient_id', 'insurance_partners',
    ];

    protected $hidden = [];

    protected $appends = ['badges'];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'opening_hours' => 'array',
            'insurance_partners' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'avg_rating' => 'decimal:1',
            'is_verified' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function getCoverImageUrlAttribute($value): ?string
    {
        if (!$value) return null;
        return str_starts_with($value, 'http') ? $value : url($value);
    }

    public function getLogoUrlAttribute($value): ?string
    {
        if (!$value) return null;
        return str_starts_with($value, 'http') ? $value : url($value);
    }

    public function getBadgesAttribute(): array
    {
        return app(\App\Services\BadgeService::class)->getBadges($this);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function catalogItems(): HasMany
    {
        return $this->hasMany(CatalogItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function insuranceTags(): HasMany
    {
        return $this->hasMany(SupplierInsuranceTag::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function bankAccount(): HasOne
    {
        return $this->hasOne(SupplierBankAccount::class);
    }

    public function scopeNearby(Builder $query, float $lat, float $lng, float $radiusKm = 50): Builder
    {
        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(latitude))
            * cos(radians(longitude) - radians(?)) + sin(radians(?))
            * sin(radians(latitude))))";

        // ETA: assume average urban speed of 45 km/h → minutes = distance_km / 45 * 60
        return $query
            ->select('suppliers.*')
            ->selectRaw("{$haversine} AS distance", [$lat, $lng, $lat])
            ->selectRaw("ROUND(({$haversine} / 45) * 60) AS eta_minutes", [$lat, $lng, $lat])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->havingRaw("distance < ?", [$radiusKm]);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved');
    }
}
