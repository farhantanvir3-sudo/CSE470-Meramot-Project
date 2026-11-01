<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ShopReview;
use App\Models\RepairShop;
use App\Models\User;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::where('role', 'customer')->first();
        if (!$customer) return;

        $reviews = [
            [
                'shop_slug' => 'protech-solutions',
                'rating' => 5,
                'comment' => 'Replaced my MacBook Air M2 display panel in less than 3 hours. OEM quality, True Tone working properly. Highly recommended!',
            ],
            [
                'shop_slug' => 'protech-solutions',
                'rating' => 5,
                'comment' => 'Fixed a severe logic board short after water spill. Clean soldering work and gave me a 6-month warranty receipt.',
            ],
            [
                'shop_slug' => 'the-gadget-den',
                'rating' => 5,
                'comment' => 'Super fast battery swap for my iPhone 13 Pro. 100% battery health restored. Fair inspection fee.',
            ],
            [
                'shop_slug' => 'the-gadget-den',
                'rating' => 4,
                'comment' => 'Great keyboard replacement on my ThinkPad. Keys feel crisp. Took slightly longer than quoted but overall happy.',
            ],
            [
                'shop_slug' => 'budget-fixer',
                'rating' => 5,
                'comment' => 'Affordable and genuine. They charged only ৳100 inspection and resolved the display ribbon loose issue in Dhanmondi.',
            ],
            [
                'shop_slug' => 'macmaster-repairs',
                'rating' => 5,
                'comment' => 'True Apple specialists in Mohakhali. Fixed my iMac power supply when other shops said it was impossible.',
            ],
            [
                'shop_slug' => 'dhaka-device-doctors',
                'rating' => 5,
                'comment' => 'Best motherboard chip-level repair in Dhanmondi! Very transparent with parts cost.',
            ],
        ];

        foreach ($reviews as $rev) {
            $shop = RepairShop::where('slug', $rev['shop_slug'])->first();
            if ($shop) {
                ShopReview::updateOrCreate(
                    [
                        'repair_shop_id' => $shop->id,
                        'customer_id' => $customer->id,
                        'review_comment' => $rev['comment'],
                    ],
                    [
                        'rating' => $rev['rating'],
                        'is_verified_purchase' => true,
                    ]
                );
            }
        }
    }
}
