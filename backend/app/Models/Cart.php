<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R8: Unified Shop-Scoped Direct-Order & Quickmatch Cart (Cart)
 * ============================================================================
 * Merged Architecture: Farhan Tanvir [22299179] & Shanid Sajjatuz Islam [23201465]
 * Maps to `carts` table in PostgreSQL (status: ACTIVE, CHECKED_OUT, ABANDONED).
 * ============================================================================
 */
class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shop()
    {
        return $this->belongsTo(RepairShop::class, 'shop_id');
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}
