<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R8: Unified Shop-Scoped Direct-Order Cart Item (CartItem)
 * ============================================================================
 * Merged Architecture: Farhan Tanvir [22299179] & Shanid Sajjatuz Islam [23201465]
 * Maps to `cart_items` table in PostgreSQL.
 * ============================================================================
 */
class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'service_name',
        'description',
        'price',
        'quantity',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'metadata' => 'array',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }
}
