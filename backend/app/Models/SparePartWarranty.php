<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Separate warranty registry storing exclusively spare-part listings that carry warranty coverage.
 * Listings without warranty have no row in `spare_part_warranties`.
 */
class SparePartWarranty extends Model
{
    use HasFactory;

    public const COVERAGE_FULL_PROTECTION = 'FULL_PROTECTION';
    public const COVERAGE_REPLACEMENT_OR_REPAIR = 'REPLACEMENT_OR_REPAIR';
    public const COVERAGE_REPLACEMENT_ONLY = 'REPLACEMENT_ONLY';
    public const COVERAGE_REFUND_OR_REPLACEMENT = 'REFUND_OR_REPLACEMENT';
    public const COVERAGE_REPAIR_ONLY = 'REPAIR_ONLY';

    public const COVERAGE_META = [
        self::COVERAGE_FULL_PROTECTION => [
            'label' => 'Full Protection (Replacement, Free Repair or Refund)',
            'covers_refund' => true,
            'covers_replacement' => true,
            'covers_free_repair' => true,
        ],
        self::COVERAGE_REPLACEMENT_OR_REPAIR => [
            'label' => 'Replacement or Free Repair',
            'covers_refund' => false,
            'covers_replacement' => true,
            'covers_free_repair' => true,
        ],
        self::COVERAGE_REPLACEMENT_ONLY => [
            'label' => 'Replacement Only',
            'covers_refund' => false,
            'covers_replacement' => true,
            'covers_free_repair' => false,
        ],
        self::COVERAGE_REFUND_OR_REPLACEMENT => [
            'label' => 'Replacement or Refund',
            'covers_refund' => true,
            'covers_replacement' => true,
            'covers_free_repair' => false,
        ],
        self::COVERAGE_REPAIR_ONLY => [
            'label' => 'Free Repair Only',
            'covers_refund' => false,
            'covers_replacement' => false,
            'covers_free_repair' => true,
        ],
    ];

    protected $table = 'spare_part_warranties';

    protected $fillable = [
        'spare_part_listing_id',
        'shop_id',
        'vendor_profile_id',
        'duration_days',
        'coverage_type',
        'covers_refund',
        'covers_replacement',
        'covers_free_repair',
        'terms',
    ];

    protected $casts = [
        'spare_part_listing_id' => 'integer',
        'shop_id' => 'integer',
        'vendor_profile_id' => 'integer',
        'duration_days' => 'integer',
        'covers_refund' => 'boolean',
        'covers_replacement' => 'boolean',
        'covers_free_repair' => 'boolean',
    ];

    public static function normalizeCoverageType(?string $type): string
    {
        $upper = strtoupper(trim((string) $type));
        return array_key_exists($upper, self::COVERAGE_META) ? $upper : self::COVERAGE_FULL_PROTECTION;
    }

    /**
     * @return array{label: string, covers_refund: bool, covers_replacement: bool, covers_free_repair: bool}
     */
    public static function metaForType(?string $type): array
    {
        return self::COVERAGE_META[self::normalizeCoverageType($type)];
    }

    /**
     * @return array<int, string>
     */
    public static function remediesForFlags(bool $replacement, bool $freeRepair, bool $refund): array
    {
        $badges = [];
        if ($replacement) {
            $badges[] = 'Replacement';
        }
        if ($freeRepair) {
            $badges[] = 'Free Repair';
        }
        if ($refund) {
            $badges[] = 'Refund';
        }
        return $badges;
    }

    /**
     * @return array<int, string>
     */
    public function remedyBadges(): array
    {
        return self::remediesForFlags(
            (bool) $this->covers_replacement,
            (bool) $this->covers_free_repair,
            (bool) $this->covers_refund
        );
    }

    public function coverageLabel(): string
    {
        return self::metaForType($this->coverage_type)['label'];
    }

    public function listing()
    {
        return $this->belongsTo(SparePartListing::class, 'spare_part_listing_id');
    }

    public function shop()
    {
        return $this->belongsTo(RepairShop::class, 'shop_id');
    }

    public function vendorProfile()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_profile_id');
    }
}
