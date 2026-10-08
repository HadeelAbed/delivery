<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'status',
        'is_online',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    public function merchantProfile(): HasOne
    {
        return $this->hasOne(Merchant::class, 'user_id');
    }

    public function driverProfile(): HasOne
    {
        return $this->hasOne(DriverProfile::class, 'user_id');
    }

    public function merchantOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'merchant_id');
    }

    public function customerOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function merchantFavorites(): HasMany
    {
        return $this->hasMany(MerchantFavorite::class, 'user_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'driver_id');
    }

    public function scopeMerchant($query)
    {
        return $query->where('role', UserRole::Merchant->value);
    }

    public function scopeDriver($query)
    {
        return $query->where('role', UserRole::Driver->value);
    }

    public function scopePending($query)
    {
        return $query->where('status', UserStatus::Pending->value);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isApproved(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
