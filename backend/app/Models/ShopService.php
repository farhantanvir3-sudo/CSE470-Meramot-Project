<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopService extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'slug',
        'name',
        'short_description',
        'description',
        'device_type',
        'issue_category',
        'pricing_type',
        'base_price',
        'price_max',
        'estimated_days_min',
        'estimated_days_max',
        'includes_pickup',
        'includes_delivery',
        'sort_order',
        'is_featured',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'price_max' => 'decimal:2',
        'includes_pickup' => 'boolean',
        'includes_delivery' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function shop()
    {
        return $this->belongsTo(RepairShop::class, 'shop_id');
    }
}
