<?php

namespace Database\Seeders;

use App\Models\RepairRequest;
use App\Models\ServicePackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServicePackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'title' => 'Virus, Malware & Adware Troubleshoot',
                'description' => 'Complete software threat eradication, browser hijack cleanup, background spyware removal, and security hardening—flat rate for any device.',
                'device_category' => 'All Devices',
                'fixed_price' => 800.00,
                'estimated_turnaround_hours' => 2,
                'warranty_months' => 1,
                'inclusions' => [
                    'Deep rootkit, malware & adware scan and removal',
                    'Browser hijack, extension & telemetry cleanup',
                    'Startup scheduler & system health optimization',
                    'Security patch & permission hardening check',
                ],
                'is_popular' => true,
            ],
            [
                'title' => 'Android Rooting & Firmware Flash',
                'description' => 'Safe bootloader unlock, verified stock or custom ROM flashing, Magisk/KernelSU root setup, and EFS backup regardless of device spec.',
                'device_category' => 'Smartphone',
                'fixed_price' => 1200.00,
                'estimated_turnaround_hours' => 2,
                'warranty_months' => 1,
                'inclusions' => [
                    'Full EFS / persist partition backup before flash',
                    'Official bootloader unlock & clean firmware flash',
                    'Magisk / KernelSU root configuration & module test',
                    'Bootloop recovery & Play Integrity verification',
                ],
                'is_popular' => true,
            ],
            [
                'title' => 'Clean OS Installation & Drivers Setup',
                'description' => 'Fresh Windows, macOS, or Linux operating system installation with complete OEM driver suite, BIOS update, and zero bloatware.',
                'device_category' => 'Laptop',
                'fixed_price' => 1400.00,
                'estimated_turnaround_hours' => 2,
                'warranty_months' => 2,
                'inclusions' => [
                    'Fresh verified OS install (Windows / Linux / macOS)',
                    'Complete OEM chipset, Wi-Fi, audio & GPU drivers',
                    'Official BIOS / UEFI firmware safety update',
                    'Power profile & thermal fan curve optimization',
                ],
                'is_popular' => true,
            ],
            [
                'title' => 'Thermal Repasting & Internal Dust Deep Clean',
                'description' => 'Preventive cooling maintenance with high-conductivity thermal compound application and full heatsink/vent dust extraction.',
                'device_category' => 'Laptop',
                'fixed_price' => 1500.00,
                'estimated_turnaround_hours' => 2,
                'warranty_months' => 3,
                'inclusions' => [
                    'High-conductivity PTM / MX thermal paste application',
                    'Dual-fan blade & copper fin dust extraction',
                    'Intake mesh & exhaust channel sanitization',
                    '30-minute load stress-test temperature verification',
                ],
                'is_popular' => true,
            ],
            [
                'title' => 'Encrypted Data Backup & OS Recovery',
                'description' => 'Corrupted boot sector repair and encrypted logical data migration onto your target storage without touching hardware.',
                'device_category' => 'All Devices',
                'fixed_price' => 1800.00,
                'estimated_turnaround_hours' => 3,
                'warranty_months' => 1,
                'inclusions' => [
                    'Read-only logical extraction from unbootable OS',
                    'Partition table, EFI & BCD boot-loader repair',
                    'Encrypted archive transfer with SHA-256 checksum',
                    'Complete post-recovery file readability check',
                ],
                'is_popular' => false,
            ],
            [
                'title' => 'Ultrasonic Liquid-Spill Chemical Cleaning',
                'description' => 'Emergency board-level chemical decontamination bath and corrosion neutralization (excludes any replacement spare parts if needed).',
                'device_category' => 'All Devices',
                'fixed_price' => 2200.00,
                'estimated_turnaround_hours' => 4,
                'warranty_months' => 1,
                'inclusions' => [
                    '99% isopropyl ultrasonic corrosion wash',
                    'Microscope flux-free oxidation neutralization',
                    'Connector pin contact & ribbon de-oxidation',
                    'Full rail short-circuit triage & drying cycle',
                ],
                'is_popular' => false,
            ],
        ];

        $keepIds = [];
        $titleToId = [];

        foreach ($packages as $pkg) {
            $saved = ServicePackage::updateOrCreate(
                ['title' => $pkg['title']],
                array_merge($pkg, ['is_active' => true])
            );
            $keepIds[] = $saved->id;
            $titleToId[$pkg['title']] = $saved->id;
        }

        // Migrate any historical/demo DIRECT_SERVICE RepairRequests pointing to retired variable-hardware packages
        $legacyMap = [
            'Original Display Panel Replacement' => 'Android Rooting & Firmware Flash',
            'Laptop High-Capacity Battery Replacement' => 'Thermal Repasting & Internal Dust Deep Clean',
            'Motherboard Micro-Soldering & IC Diagnostic' => 'Ultrasonic Liquid-Spill Chemical Cleaning',
            'Gaming Console Thermal Overhaul & Fan Deep Clean' => 'Thermal Repasting & Internal Dust Deep Clean',
            'Mechanical & Chiclet Keyboard Replacement' => 'Clean OS Installation & Drivers Setup',
            'Type-C / Lightning Port Microsoldering Fix' => 'Virus, Malware & Adware Troubleshoot',
        ];

        $legacyPackages = ServicePackage::whereNotIn('id', $keepIds)->get(['id', 'title']);
        if ($legacyPackages->isNotEmpty()) {
            $fallbackId = $titleToId['Virus, Malware & Adware Troubleshoot'];
            foreach ($legacyPackages as $oldPkg) {
                $targetTitle = $legacyMap[$oldPkg->title] ?? 'Virus, Malware & Adware Troubleshoot';
                $targetId = $titleToId[$targetTitle] ?? $fallbackId;

                RepairRequest::where('requested_service_id', $oldPkg->id)
                    ->update(['requested_service_id' => $targetId]);
            }

            ServicePackage::whereNotIn('id', $keepIds)->delete();
        }

        // Keep ServicePackage.order_count strictly synchronized with DIRECT_SERVICE RepairRequests
        $countsByPkg = RepairRequest::query()
            ->where('source', 'DIRECT_SERVICE')
            ->whereIn('requested_service_id', $keepIds)
            ->selectRaw('requested_service_id, COUNT(*) as agg')
            ->groupBy('requested_service_id')
            ->pluck('agg', 'requested_service_id');

        foreach ($keepIds as $pkgId) {
            ServicePackage::where('id', $pkgId)->update([
                'order_count' => (int) ($countsByPkg[$pkgId] ?? 0),
            ]);
        }

        // Remove retired variable-part promo banner if previously seeded
        DB::table('offer_banners')
            ->where('title', 'Same-Day Certified Display & Battery Packages')
            ->delete();

        $banners = [
            [
                'title' => 'Same-Day Software, OS & Maintenance Packages',
                'subtitle' => 'Flat-rate virus troubleshooting, OS setup, device rooting & thermal deep cleaning at verified labs.',
                'cta_label' => 'Browse Fixed Packages',
                'cta_href' => '/packages/catalog',
                'background_color' => '#064e3b',
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
