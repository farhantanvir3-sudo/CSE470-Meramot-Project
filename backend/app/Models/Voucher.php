<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Unified Voucher model supporting:
 *   - Two recurrence types: `LIMITED_TIME` (bounded dates and/or recurring days of week)
 *     and `USE_ONCE` (1 redemption per customer, optionally `is_first_order_only` across site).
 *   - Two issuer actors:
 *       1. Platform Admin ("Featured Voucher", `issuer_type = ADMIN`, minus `excludedShops`)
 *       2. Vendor Owner ("Voucher", `issuer_type = VENDOR`, `ALL_BRANCHES` or `SPECIFIC_BRANCHES`)
 *   - Three benefit types: `PERCENTAGE`, `FIXED`, and `FREE_COURIER` (doorstep pickup & delivery).
 */
class Voucher extends Model
{
    use HasFactory;

    public const TYPE_LIMITED_TIME = 'LIMITED_TIME';
    public const TYPE_USE_ONCE = 'USE_ONCE';

    public const ISSUER_ADMIN = 'ADMIN';
    public const ISSUER_VENDOR = 'VENDOR';

    public const SCOPE_UNIVERSAL = 'UNIVERSAL';
    public const SCOPE_ALL_BRANCHES = 'ALL_BRANCHES';
    public const SCOPE_SPECIFIC_BRANCHES = 'SPECIFIC_BRANCHES';

    public const DISCOUNT_PERCENTAGE = 'PERCENTAGE';
    public const DISCOUNT_FIXED = 'FIXED';
    public const DISCOUNT_FREE_COURIER = 'FREE_COURIER';

    public const DEFAULT_FREE_COURIER_CODE = 'FREECOURIER';

    public const DAY_NAMES = [
        0 => 'Sundays',
        1 => 'Mondays',
        2 => 'Tuesdays',
        3 => 'Wednesdays',
        4 => 'Thursdays',
        5 => 'Fridays',
        6 => 'Saturdays',
    ];

    public const DAY_SHORT_NAMES = [
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
    ];

    public const PAYMENT_LABELS = [
        'BKASH' => 'bKash',
        'NAGAD' => 'Nagad',
        'ROCKET' => 'Rocket',
        'CARD' => 'Card',
        'SSLCOMMERZ' => 'SSLCommerz',
        'CASH' => 'Cash',
    ];

    protected $table = 'vouchers';

    protected $fillable = [
        'code',
        'title',
        'banner_subtitle',
        'description',
        'cta_href',
        'voucher_type',
        'issuer_type',
        'branch_scope',
        'created_by_user_id',
        'vendor_profile_id',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount_amount',
        'required_payment_method',
        'is_first_order_only',
        'is_system_default',
        'is_featured',
        'featured_order',
        'starts_at',
        'expires_at',
        'valid_days_of_week',
        'daily_start_time',
        'daily_end_time',
        'is_active',
        'usage_count',
    ];

    protected $casts = [
        'created_by_user_id' => 'integer',
        'vendor_profile_id' => 'integer',
        'discount_value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'is_first_order_only' => 'boolean',
        'is_system_default' => 'boolean',
        'is_featured' => 'boolean',
        'featured_order' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'valid_days_of_week' => 'array',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
    ];

    public function vendorProfile()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_profile_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function includedShops()
    {
        return $this->belongsToMany(RepairShop::class, 'voucher_shops', 'voucher_id', 'shop_id')
            ->withTimestamps();
    }

    public function excludedShops()
    {
        return $this->belongsToMany(RepairShop::class, 'voucher_excluded_shops', 'voucher_id', 'shop_id')
            ->withTimestamps();
    }

    public function redemptions()
    {
        return $this->hasMany(VoucherRedemption::class, 'voucher_id');
    }

    public function isWithinDateWindow(?CarbonInterface $at = null): bool
    {
        if (!$this->is_active) {
            return false;
        }
        $now = $at ?? now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }
        if ($this->expires_at && $now->gt($this->expires_at)) {
            return false;
        }
        return true;
    }

    public function normalizedDaysOfWeek(): array
    {
        if (!is_array($this->valid_days_of_week) || empty($this->valid_days_of_week)) {
            return [];
        }

        $days = array_values(array_unique(array_filter(
            array_map('intval', $this->valid_days_of_week),
            fn (int $d) => $d >= 0 && $d <= 6
        )));
        sort($days);

        return $days;
    }

    public function isScheduledNow(?CarbonInterface $at = null): bool
    {
        $now = $at ? Carbon::instance($at->toDateTime()) : now();
        $days = $this->normalizedDaysOfWeek();
        if (!empty($days) && !in_array((int) $now->dayOfWeek, $days, true)) {
            return false;
        }

        if (!empty($this->daily_start_time) && !empty($this->daily_end_time)) {
            $hhmm = $now->format('H:i');
            if ($hhmm < $this->daily_start_time || $hhmm > $this->daily_end_time) {
                return false;
            }
        }

        return true;
    }

    public function paymentMethodLabel(): ?string
    {
        if (empty($this->required_payment_method)) {
            return null;
        }
        $key = strtoupper(trim((string) $this->required_payment_method));
        return self::PAYMENT_LABELS[$key] ?? $key;
    }

    /**
     * Format percentage or currency amount cleanly without trailing `.00`.
     */
    public static function cleanNumber(float $val): string
    {
        if (abs($val - round($val)) < 0.005) {
            return number_format(round($val), 0);
        }
        return rtrim(rtrim(number_format($val, 2), '0'), '.');
    }

    /**
     * Foodpanda Alfresco-inspired uniform shop-tile & profile badge label.
     * Customer sees identical format whether issued by Admin or Vendor:
     *   - "৳150 off ৳1,000: PROTECH150"
     *   - "10% off (bKash): BKASH10"
     *   - "15% off: BATTERY15"
     *   - "Free Courier (1st Order): FREECOURIER"
     */
    public function advertisedBadgeText(): string
    {
        $minSpend = (float) $this->min_order_amount;
        $val = (float) $this->discount_value;
        $payLabel = $this->paymentMethodLabel();

        if ($this->discount_type === self::DISCOUNT_FREE_COURIER) {
            if ($this->is_first_order_only) {
                $prefix = 'Free Courier (1st Order)';
            } elseif ($minSpend > 0) {
                $prefix = 'Free Courier ৳' . self::cleanNumber($minSpend) . '+';
            } else {
                $prefix = 'Free Courier';
            }
            if ($payLabel) {
                $prefix .= " ({$payLabel})";
            }
            return "{$prefix}: {$this->code}";
        }

        if ($this->discount_type === self::DISCOUNT_PERCENTAGE) {
            $pct = self::cleanNumber($val);
            if ($payLabel) {
                $prefix = "{$pct}% off ({$payLabel})";
            } elseif ($minSpend > 0) {
                $prefix = "{$pct}% off ৳" . self::cleanNumber($minSpend);
            } else {
                $prefix = "{$pct}% off";
            }
            return "{$prefix}: {$this->code}";
        }

        // FIXED amount off
        $amt = self::cleanNumber($val);
        if ($minSpend > 0) {
            $prefix = "৳{$amt} off ৳" . self::cleanNumber($minSpend);
        } else {
            $prefix = "৳{$amt} off";
        }
        if ($payLabel) {
            $prefix .= " ({$payLabel})";
        }
        return "{$prefix}: {$this->code}";
    }

    /**
     * Bold highlight shown on the Foodpanda-style voucher card.
     */
    public function discountHighlightLabel(): string
    {
        if ($this->discount_type === self::DISCOUNT_FREE_COURIER) {
            return 'Free Courier';
        }
        if ($this->discount_type === self::DISCOUNT_PERCENTAGE) {
            return self::cleanNumber((float) $this->discount_value) . '% OFF';
        }
        return '৳' . self::cleanNumber((float) $this->discount_value);
    }

    public function scheduleSummaryLabel(): string
    {
        $parts = [];
        $days = $this->normalizedDaysOfWeek();
        if (!empty($days) && count($days) < 7) {
            if (count($days) === 1) {
                $parts[] = 'Every ' . rtrim(self::DAY_NAMES[$days[0]] ?? 'Week', 's');
            } else {
                $parts[] = implode(', ', array_map(fn ($d) => self::DAY_SHORT_NAMES[$d] ?? '', $days));
            }
        }

        if (!empty($this->daily_start_time) && !empty($this->daily_end_time)) {
            $parts[] = "{$this->daily_start_time}–{$this->daily_end_time}";
        }

        if ($this->expires_at) {
            $parts[] = 'Use by ' . $this->expires_at->format('j M Y');
        } elseif ($this->is_first_order_only) {
            $parts[] = '1st order on Meramot';
        } elseif ($this->voucher_type === self::TYPE_USE_ONCE) {
            $parts[] = 'One-time use';
        }

        return !empty($parts) ? implode(' · ', $parts) : 'Ongoing offer';
    }

    public function conditionsPillLabel(): string
    {
        $items = [];
        $minSpend = (float) $this->min_order_amount;
        if ($minSpend > 0) {
            $items[] = 'Min. spend ৳' . self::cleanNumber($minSpend);
        } elseif ($this->is_first_order_only) {
            $items[] = 'First order only';
        } elseif ($this->discount_type === self::DISCOUNT_FREE_COURIER) {
            $items[] = 'Courier pickup & return';
        }

        if ($payLabel = $this->paymentMethodLabel()) {
            $items[] = "Pay with {$payLabel}";
        }

        if ($this->discount_type === self::DISCOUNT_PERCENTAGE && (float) ($this->max_discount_amount ?? 0) > 0) {
            $items[] = 'Up to ৳' . self::cleanNumber((float) $this->max_discount_amount);
        }

        $sched = $this->scheduleSummaryLabel();
        if ($sched !== '' && !in_array($sched, $items, true) && !($this->is_first_order_only && $sched === '1st order on Meramot')) {
            $items[] = $sched;
        }

        return implode(' · ', array_slice($items, 0, 3));
    }
}
