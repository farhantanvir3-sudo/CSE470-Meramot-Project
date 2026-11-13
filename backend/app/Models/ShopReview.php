<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R9: Vendor Rating & Verified Repair Review System
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
 * Architectural Layer: Model (ShopReview)
 * Associated Controller: ReviewController
 * View: resources/views/reviews/write-review.blade.php
 * ============================================================================
 */
class ShopReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_shop_id',
        'customer_id',
        'repair_request_id',
        'rating', // 1 to 5 stars
        'service_provided',
        'review_comment',
        'is_verified_purchase',
        'is_hidden',
        'moderated_at',
        'moderated_by_id',
        'moderation_note',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_verified_purchase' => 'boolean',
        'is_hidden' => 'boolean',
        'moderated_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function shop()
    {
        return $this->belongsTo(RepairShop::class, 'repair_shop_id');
    }

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class, 'repair_request_id');
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderated_by_id');
    }

    public const OFFENSIVE_WORDS = [
        'idiot', 'idiots', 'stupid', 'moron', 'morons', 'garbage', 'trash',
        'pathetic', 'useless', 'fool', 'fools', 'bastard', 'bastards', 'scumbag',
        'scammers', 'frauds', 'cheaters', 'rubbish', 'crap', 'bullshit',
        'shit', 'fuck', 'fucking', 'asshole', 'bitch', 'wtf', 'dumb', 'lunatic',
    ];

    public static function detectOffensiveWords(?string $text): array
    {
        $content = strtolower((string) $text);
        if ($content === '') {
            return [];
        }

        $found = [];
        foreach (self::OFFENSIVE_WORDS as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $content)) {
                $found[] = $word;
            }
        }

        return array_values(array_unique($found));
    }

    public static function containsOffensiveLanguage(?string $text): bool
    {
        return count(self::detectOffensiveWords($text)) > 0;
    }
}
