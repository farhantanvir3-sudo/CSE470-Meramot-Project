<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'name',
        'email',
        'google_id',
        'auth_provider',
        'password',
        'phone',
        'avatar_url',
        'address',
        'city',
        'area',
        'latitude',
        'longitude',
        'role', // 'customer', 'vendor', 'admin', 'delivery', 'delivery_admin'
        'is_email_verified',
        'is_phone_verified',
        'email_otp_code',
        'email_otp_expires_at',
        'phone_otp_code',
        'phone_otp_expires_at',
        'status',
        'pending_penalty',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_email_verified' => 'boolean',
        'is_phone_verified' => 'boolean',
        'email_otp_expires_at' => 'datetime',
        'phone_otp_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'pending_penalty' => 'decimal:2',
    ];

    public function vendorProfile()
    {
        return $this->hasOne(VendorProfile::class);
    }

    public function repairRequests()
    {
        return $this->hasMany(RepairRequest::class, 'customer_id');
    }

    public function deliveryAgent()
    {
        return $this->hasOne(DeliveryAgent::class);
    }
}
