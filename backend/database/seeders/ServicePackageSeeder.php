<?php

namespace Database\Seeders;

use App\Models\ServicePackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Meramot Service Package Seeder — Feature R8
 * Fixed-price pre-packaged repair services from legacy Meramot
 */
class ServicePackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'title' => 'Original Display Panel Replacement',
                'device_category' => 'Smartphone',
                'fixed_price' => 2800.00,
                'estimated_turnaround_hours' => 2,
                'warranty_months' => 6,
                'inclusions' => [
                    'Genuine OLED / IPS display assembly',
                    'Dust-free clean room vacuum fitting',
                    'Digitizer touch sensitivity test',
                    'Free screen protector application'
                ],
                'is_popular' => true,
            ],
            [
                'title' => 'Laptop High-Capacity Battery Replacement',
                'device_category' => 'Laptop',
                'fixed_price' => 3200.00,
                'estimated_turnaround_hours' => 3,
                'warranty_months' => 12,
                'inclusions' => [
                    'Grade-A certified lithium-polymer cell',
                    'Power controller calibration',
                    'Full charge-discharge cycle diagnostic',
                    'Safe disposal of degraded battery'
                ],
                'is_popular' => true,
            ],
            [
                'title' => 'Motherboard Micro-Soldering & IC Diagnostic',
                'device_category' => 'Laptop',
                'fixed_price' => 4500.00,
                'estimated_turnaround_hours' => 24,
                'warranty_months' => 3,
                'inclusions' => [
                    'Oscilloscope short-circuit tracing',
                    'SMD capacitor & MOSFET replacement',
                    'Ultrasonic chemical cleaning',
                    '48-hour thermal stress testing'
                ],
                'is_popular' => false,
            ],
            [
                'title' => 'Gaming Console Thermal Overhaul & Fan Deep Clean',
                'device_category' => 'Gaming Console',
                'fixed_price' => 1800.00,
                'estimated_turnaround_hours' => 4,
                'warranty_months' => 3,
                'inclusions' => [
                    'Complete disassembly & dust extraction',
                    'High-performance liquid metal / Arctic MX-6 repasting',
                    'Heatsink fin chemical wash',
                    'Noise decibel & exhaust CFM measurement'
                ],
                'is_popular' => true,
            ],
            [
                'title' => 'Mechanical & Chiclet Keyboard Replacement',
                'device_category' => 'Laptop',
                'fixed_price' => 2200.00,
                'estimated_turnaround_hours' => 3,
                'warranty_months' => 6,
                'inclusions' => [
                    'OEM backlit keyboard unit',
                    'Ribbon connector inspection',
                    'Full key-press actuation test',
                    'Top palm-rest re-alignment'
                ],
                'is_popular' => false,
            ],
            [
                'title' => 'Type-C / Lightning Port Microsoldering Fix',
                'device_category' => 'Smartphone',
                'fixed_price' => 1200.00,
                'estimated_turnaround_hours' => 1,
                'warranty_months' => 3,
                'inclusions' => [
                    'Fast-charge PD pin continuity check',
                    'New gold-plated USB female connector',
                    'Microscope-guided solder reinforcement',
                    'OTG data transfer verification'
                ],
                'is_popular' => true,
            ],
        ];

        foreach ($packages as $pkg) {
            ServicePackage::updateOrCreate(
                ['title' => $pkg['title']],
                array_merge($pkg, [
                    'is_active' => true,
                    'order_count' => 0,
                ])
            );
        }

        // Seed active promotional offer banners interconnected with live routes
        $banners = [
            [
                'title' => 'Same-Day Certified Display & Battery Packages',
                'subtitle' => 'Transparent fixed-price servicing with 90–365 day Meramot escrow warranty.',
                'cta_label' => 'Browse Fixed Packages',
                'cta_href' => '/packages/catalog',
                'background_color' => '#0f3d2e',
                'position' => 1,
                'section_type' => 'HERO_OFFER',
            ],
            [
                'title' => 'Doorstep Courier Pickup Across Dhaka & Chattogram',
                'subtitle' => 'OTP-verified rider pickup and ESD-safe return delivery on verified workshops.',
                'cta_label' => 'Explore Pickup Shops',
                'cta_href' => '/shops/explore?has_voucher=1',
                'background_color' => '#14532d',
                'position' => 2,
                'section_type' => 'HERO_OFFER',
            ],
            [
                'title' => 'Unsure What Failed? Compare Itemized Technician Quotes',
                'subtitle' => 'Post a diagnostic request and compare parts + labor breakdowns before committing.',
                'cta_label' => 'Post Repair Request',
                'cta_href' => '/requests/create',
                'background_color' => '#1e3a2f',
                'position' => 3,
                'section_type' => 'HERO_OFFER',
            ],
        ];

        foreach ($banners as $banner) {
            DB::table('offer_banners')->updateOrInsert(
                ['title' => $banner['title']],
                array_merge($banner, [
                    'is_active' => true,
                    'starts_at' => now()->subDays(30),
                    'ends_at' => now()->addMonths(6),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
