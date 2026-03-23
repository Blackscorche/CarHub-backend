<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'cpf',
        'avatar_url',
        'status',
        'fcm_token',
        'lgpd_consent',
        'lgpd_consent_at',
        'terms_version',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'lgpd_consent' => 'boolean',
            'lgpd_consent_at' => 'datetime',
        ];
    }

    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function cashbackWallet(): HasOne
    {
        return $this->hasOne(CashbackWallet::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSupplier(): bool
    {
        return $this->role === 'supplier';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }
}
