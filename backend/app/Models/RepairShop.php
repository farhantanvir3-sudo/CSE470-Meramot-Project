<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R6: Advanced Repair Shop Search & Multi-Parameter Filtering
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Multi-Branch & Dual-Address Architecture: Farhan Tanvir [Student ID: 22299179]
 * Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
 * Architectural Layer: Model (RepairShop)
 * Associated Controller: ShopDiscoveryController, VendorOnboardingController
 * View: frontend/views/shops/explore.blade.php, frontend/views/shops/show.blade.php
 * ============================================================================
 */
class RepairShop extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_profile_id',
        'osm_id',
        'name',
        'slug',
        'is_branch',
        'branch_name',
        'description',
        'address',
        'gps_address',
        'same_as_gps_location',
        'city',
        'area',
        'latitude',
        'longitude',
        'rating',
        'review_count',
        'price_level',
        'inspection_fee',
        'base_labor_fee',
        'pickup_fee',
        'express_fee',
        'opening_days',
        'opening_hours',
        'opening_hours_text',
        'offer_summary',
        'result_tag',
        'eta_minutes',
        'has_deals',
        'has_voucher',
        'free_delivery',
        'is_featured',
        'featured_at',
        'is_active',
        'is_public',
        'setup_complete',
        'accepts_direct_orders',
        'supports_pickup',
        'supports_onsite_repair',
        'delivery_radius_km',
        'phone',
        'email',
        'logo_url',
        'banner_url',
        'categories',
        'specialties',
    ];

    /**
     * Hide private GPS approximate location text from raw public JSON serialization
     * while keeping Haversine distance calculations operational server-side.
     */
    protected $hidden = [
        'gps_address',
    ];

    protected $casts = [
        'is_branch' => 'boolean',
        'same_as_gps_location' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'rating' => 'decimal:2',
        'review_count' => 'integer',
        'price_level' => 'integer',
        'inspection_fee' => 'decimal:2',
        'base_labor_fee' => 'decimal:2',
        'pickup_fee' => 'decimal:2',
        'express_fee' => 'decimal:2',
        'delivery_radius_km' => 'decimal:2',
        'eta_minutes' => 'integer',
        'has_deals' => 'boolean',
        'is_featured' => 'boolean',
        'featured_at' => 'datetime',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'setup_complete' => 'boolean',
        'accepts_direct_orders' => 'boolean',
        'supports_pickup' => 'boolean',
        'supports_onsite_repair' => 'boolean',
        'has_voucher' => 'boolean',
        'free_delivery' => 'boolean',
        'categories' => 'array',
        'specialties' => 'array',
    ];

    public function vendorProfile()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_profile_id');
    }

    public function reviews()
    {
        return $this->hasMany(ShopReview::class);
    }

    public function services()
    {
        return $this->hasMany(ShopService::class, 'shop_id');
    }

    public function servicePackages()
    {
        return $this->belongsToMany(ServicePackage::class, 'repair_shop_service_package')->withTimestamps();
    }

    public function carts()
    {
        return $this->hasMany(Cart::class, 'shop_id');
    }

    public function repairJobs()
    {
        return $this->hasMany(RepairJob::class, 'shop_id');
    }

    public function sparePartListings()
    {
        return $this->hasMany(SparePartListing::class, 'shop_id');
    }

    public function branchVouchers()
    {
        return $this->belongsToMany(Voucher::class, 'voucher_shops', 'shop_id', 'voucher_id')
            ->withTimestamps();
    }

    public function excludedFromVouchers()
    {
        return $this->belongsToMany(Voucher::class, 'voucher_excluded_shops', 'shop_id', 'voucher_id')
            ->withTimestamps();
    }

    /**
     * Resolved collection of active Vouchers applicable to this shop branch.
     */
    public function availableVouchers()
    {
        if ($this->relationLoaded('available_vouchers')) {
            return $this->getRelation('available_vouchers');
        }

        return app(\App\Services\VoucherService::class)->getAvailableVouchersForShop($this);
    }

    /**
     * Public vendor-provided storefront address.
     * If `same_as_gps_location` is true, returns `gps_address` (which equals `address`).
     * Otherwise returns ONLY the vendor-provided `address`, keeping raw GPS pin private.
     */
    public function getPublicAddressAttribute(): string
    {
        if ($this->same_as_gps_location && !empty($this->gps_address)) {
            return $this->gps_address;
        }

        if (!empty($this->address)) {
            return $this->address;
        }

        return collect([$this->area, $this->city])->filter()->implode(', ') ?: 'Bangladesh';
    }

    /**
     * Exact branch tag label to display on shop tiles when branching is enabled.
     */
    public function getDisplayBranchTagAttribute(): ?string
    {
        if ($this->is_branch && !empty($this->branch_name)) {
            return $this->branch_name;
        }

        return null;
    }

    /**
     * Fetch other active branches owned by the same vendor brand.
     */
    public function siblingBranches()
    {
        if (!$this->vendor_profile_id) {
            return collect();
        }

        return static::where('vendor_profile_id', $this->vendor_profile_id)
            ->where('id', '!=', $this->id)
            ->where('is_active', true)
            ->orderBy('city')
            ->orderBy('branch_name')
            ->get();
    }

    /**
     * Effective platform fee percentage (5% to 10% max) configured by Admin on this workshop.
     */
    public function effectiveCommissionRatePct(): float
    {
        $profile = $this->relationLoaded('vendorProfile')
            ? $this->vendorProfile
            : $this->vendorProfile()->first();

        if ($profile) {
            return $profile->effectiveCommissionRatePct();
        }

        return VendorProfile::DEFAULT_COMMISSION_RATE;
    }

    /**
     * Normalized uppercase capability tags stored on this storefront.
     *
     * @return string[]
     */
    private function normalizedCategoryTags(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($item) => strtoupper(trim((string) $item)),
            (array) ($this->categories ?? [])
        ))));
    }

    /**
     * Whether this workshop supports platform Courier Pickup & Return logistics.
     */
    public function supportsCourierPickup(): bool
    {
        $cats = $this->normalizedCategoryTags();
        if (!empty($cats)) {
            return in_array('COURIER_PICKUP', $cats, true);
        }

        return (bool) $this->supports_pickup;
    }

    /**
     * Whether customers can walk into this workshop for on-site repair without delivery.
     */
    public function supportsInShopRepair(): bool
    {
        $cats = $this->normalizedCategoryTags();
        if (!empty($cats)) {
            return in_array('IN_SHOP_REPAIR', $cats, true);
        }

        return (bool) ($this->supports_onsite_repair ?? true);
    }

    /**
     * Whether this storefront sells replacement hardware / wholesale spare parts.
     */
    public function supportsSpareParts(): bool
    {
        return in_array('SPARE_PARTS', $this->normalizedCategoryTags(), true);
    }

    /**
     * Whether this workshop offers model-independent Fixed-Price Repair Packages.
     */
    public function supportsFixedPackages(): bool
    {
        if ($this->relationLoaded('vendorProfile') && $this->vendorProfile && $this->vendorProfile->provides_fixed_packages === true) {
            return true;
        }

        if ($this->relationLoaded('servicePackages')) {
            return $this->servicePackages->isNotEmpty();
        }

        return $this->servicePackages()->exists();
    }

    /**
     * Whether customers can book device repairs (via courier, in-shop walk-in, or fixed packages).
     */
    public function supportsRepairBookings(): bool
    {
        return $this->supportsCourierPickup()
            || $this->supportsInShopRepair()
            || $this->supportsFixedPackages();
    }

    /**
     * Whether this store operates strictly as a Spare Parts Wholesaler / Store without repair bench services.
     */
    public function isPureSparePartsWholesaler(): bool
    {
        return $this->supportsSpareParts() && !$this->supportsRepairBookings();
    }

    /**
     * Reusable condition for spare part listings that are active and on a sale price.
     */
    public static function applySparePartsOnSaleCondition(\Illuminate\Database\Eloquent\Builder $spQ, ?\Illuminate\Support\Carbon $at = null): void
    {
        $now = $at ?: now();
        $spQ->where('is_active', true)
            ->where('offer_enabled', true)
            ->where(function ($dateQ) use ($now) {
                $dateQ->whereNull('offer_starts_at')->orWhere('offer_starts_at', '<=', $now);
            })
            ->where(function ($dateQ) use ($now) {
                $dateQ->whereNull('offer_ends_at')->orWhere('offer_ends_at', '>=', $now);
            })
            ->where(function ($priceQ) {
                $priceQ->where(function ($pq) {
                    $pq->whereNotNull('offer_price')
                        ->where('offer_price', '>', 0)
                        ->where(function ($inner) {
                            $inner->where(function ($withNorm) {
                                $withNorm->whereNotNull('normal_price')
                                    ->whereColumn('offer_price', '<', 'normal_price');
                            })->orWhere(function ($withoutNorm) {
                                $withoutNorm->whereNull('normal_price')
                                    ->whereColumn('offer_price', '<', 'base_price');
                            });
                        });
                })
                ->orWhere(function ($pq) {
                    $pq->whereNotNull('offer_discount_pct')
                        ->where('offer_discount_pct', '>', 0);
                })
                ->orWhere(function ($pq) {
                    $pq->whereNotNull('normal_price')
                        ->whereColumn('normal_price', '>', 'base_price');
                })
                ->orWhere(function ($pq) {
                    $pq->whereNotNull('regular_price')
                        ->whereColumn('regular_price', '>', 'base_price');
                });
            });
    }

    /**
     * Whether this workshop/store has active spare part listings currently on a sale price.
     */
    public function hasSparePartsOnSale(?\Illuminate\Support\Carbon $at = null): bool
    {
        if (isset($this->attributes['has_sale_parts'])) {
            return (bool) $this->attributes['has_sale_parts'];
        }

        if (!$this->supportsSpareParts()) {
            return false;
        }

        if ($this->relationLoaded('sparePartListings')) {
            return $this->sparePartListings->contains(function ($listing) use ($at) {
                return (bool) $listing->is_active
                    && (bool) $listing->isOfferActive($at)
                    && (float) $listing->resolvedNormalBasePrice() > (float) $listing->effectiveBasePrice($at);
            });
        }

        return $this->sparePartListings()
            ->where(function ($spQ) use ($at) {
                static::applySparePartsOnSaleCondition($spQ, $at);
            })
            ->exists();
    }

    /**
     * Scope query to only shops that have active spare parts on sale price.
     */
    public function scopeHasSparePartsOnSale(\Illuminate\Database\Eloquent\Builder $query, ?\Illuminate\Support\Carbon $at = null): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereHas('sparePartListings', function ($spQ) use ($at) {
            static::applySparePartsOnSaleCondition($spQ, $at);
        });
    }

    /**
     * Reusable condition for active service deals (promotional pricing on services).
     */
    public static function applyServiceDealCondition(\Illuminate\Database\Eloquent\Builder $sQ, ?\Illuminate\Support\Carbon $at = null): void
    {
        $now = $at ?: now();
        $sQ->where('is_active', true)
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

    /**
     * Whether this workshop has active promotional deals on any listed services.
     */
    public function hasActiveServiceDeals(?\Illuminate\Support\Carbon $at = null): bool
    {
        if (isset($this->attributes['has_active_service_deals'])) {
            return (bool) $this->attributes['has_active_service_deals'];
        }

        if ($this->relationLoaded('services')) {
            return $this->services->contains(function ($service) use ($at) {
                return (bool) $service->is_active && $service->isDealActive($at);
            });
        }

        return $this->services()
            ->where(function ($sQ) use ($at) {
                static::applyServiceDealCondition($sQ, $at);
            })
            ->exists();
    }

    /**
     * Scope query to only shops that have active service deals.
     */
    public function scopeHasActiveServiceDeals(\Illuminate\Database\Eloquent\Builder $query, ?\Illuminate\Support\Carbon $at = null): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereHas('services', function ($sQ) use ($at) {
            static::applyServiceDealCondition($sQ, $at);
        });
    }
}
