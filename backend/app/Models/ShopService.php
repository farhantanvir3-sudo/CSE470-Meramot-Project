<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
        'regular_price',
        'deal_enabled',
        'deal_price',
        'deal_discount_pct',
        'deal_starts_at',
        'deal_ends_at',
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
        'regular_price' => 'decimal:2',
        'deal_enabled' => 'boolean',
        'deal_price' => 'decimal:2',
        'deal_discount_pct' => 'integer',
        'deal_starts_at' => 'datetime',
        'deal_ends_at' => 'datetime',
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

    /**
     * Normal non-promotional price for this service.
     */
    public function resolvedNormalPrice(): float
    {
        if ($this->regular_price !== null && (float) $this->regular_price > 0) {
            return round((float) $this->regular_price, 2);
        }

        return round((float) ($this->base_price ?? 0), 2);
    }

    /**
     * Determine whether a promotional limited-time deal is currently active on this service.
     */
    public function isDealActive(?Carbon $at = null): bool
    {
        if (!$this->deal_enabled) {
            return false;
        }

        $now = $at ?: now();

        if ($this->deal_starts_at && $now->lt($this->deal_starts_at)) {
            return false;
        }

        if ($this->deal_ends_at && $now->gt($this->deal_ends_at)) {
            return false;
        }

        $normal = $this->resolvedNormalPrice();

        if ($this->deal_price !== null && (float) $this->deal_price > 0) {
            return $normal <= 0 || (float) $this->deal_price < $normal;
        }

        if ($this->deal_discount_pct !== null && (int) $this->deal_discount_pct > 0) {
            return true;
        }

        return false;
    }

    /**
     * Effective price for the service right now (applies deal if active).
     */
    public function effectivePrice(?Carbon $at = null): float
    {
        $normal = $this->resolvedNormalPrice();

        if ($this->isDealActive($at)) {
            if ($this->deal_price !== null && (float) $this->deal_price > 0) {
                return round((float) $this->deal_price, 2);
            }

            if ($this->deal_discount_pct !== null && (int) $this->deal_discount_pct > 0 && $normal > 0) {
                $discounted = $normal * (1 - ((int) $this->deal_discount_pct / 100));
                return round(max(0, $discounted), 2);
            }
        }

        return $normal;
    }

    /**
     * Calculate effective discount percentage for display.
     */
    public function effectiveDiscountPct(?Carbon $at = null): ?int
    {
        if (!$this->isDealActive($at)) {
            return null;
        }

        if ($this->deal_discount_pct !== null && (int) $this->deal_discount_pct > 0) {
            return (int) $this->deal_discount_pct;
        }

        $normal = $this->resolvedNormalPrice();
        $deal = (float) ($this->deal_price ?? 0);
        if ($normal > 0 && $deal > 0 && $deal < $normal) {
            return (int) round((($normal - $deal) / $normal) * 100);
        }

        return null;
    }

    /**
     * Scope to only services with an active promotional deal.
     */
    public function scopeActiveDeals(Builder $query, ?Carbon $at = null): Builder
    {
        $now = $at ?: now();

        return $query->where('is_active', true)
            ->where('deal_enabled', true)
            ->where(function ($w) use ($now) {
                $w->whereNull('deal_starts_at')->orWhere('deal_starts_at', '<=', $now);
            })
            ->where(function ($w) use ($now) {
                $w->whereNull('deal_ends_at')->orWhere('deal_ends_at', '>=', $now);
            })
            ->where(function ($w) {
                $w->where(function ($p) {
                    $p->whereNotNull('deal_price')->where('deal_price', '>', 0);
                })->orWhere(function ($pct) {
                    $pct->whereNotNull('deal_discount_pct')->where('deal_discount_pct', '>', 0);
                });
            });
    }
}
