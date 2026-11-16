<?php

namespace Database\Seeders;

use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ServicePackage;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServicePackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'title' => 'Virus, Malware & Adware Troubleshoot',
                'description' => 'Complete software threat eradication, browser hijack cleanup, spyware removal, and system security hardening for any device.',
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
                'description' => 'Safe bootloader unlock, verified stock or custom ROM flashing, Magisk/KernelSU root setup, and partition backup.',
                'device_category' => 'Smartphone',
                'fixed_price' => 1200.00,
                'estimated_turnaround_hours' => 2,
                'warranty_months' => 1,
                'inclusions' => [
                    'Full EFS & persist partition backup prior to flash',
                    'Official bootloader unlock & clean firmware flash',
                    'Magisk / KernelSU root configuration & module test',
                    'Bootloop recovery & Play Integrity verification',
                ],
                'is_popular' => true,
            ],
            [
                'title' => 'Windows & OS Clean Installation',
                'description' => 'Fresh Windows, macOS, or Linux operating system installation with complete driver suite, UEFI update, and bloatware-free tuning.',
                'device_category' => 'Laptop & PC',
                'fixed_price' => 1400.00,
                'estimated_turnaround_hours' => 2,
                'warranty_months' => 2,
                'inclusions' => [
                    'Fresh verified OS install (Windows / Linux / macOS)',
                    'Complete chipset, network, graphics & audio drivers',
                    'Official BIOS / UEFI firmware safety update',
                    'Power profile & startup responsiveness tuning',
                ],
                'is_popular' => true,
            ],
            [
                'title' => 'Thermal Repasting & Internal Dust Deep Clean',
                'description' => 'Preventive cooling maintenance with high-conductivity thermal compound application and full heatsink and vent dust extraction.',
                'device_category' => 'Laptop & PC',
                'fixed_price' => 1500.00,
                'estimated_turnaround_hours' => 2,
                'warranty_months' => 3,
                'inclusions' => [
                    'High-conductivity PTM / MX thermal compound application',
                    'Internal cooling airway & copper fin dust extraction',
                    'Intake mesh & exhaust channel sanitization',
                    '30-minute load stress-test temperature verification',
                ],
                'is_popular' => true,
            ],
            [
                'title' => 'System Tune-Up & Preventive Maintenance',
                'description' => 'Comprehensive software health audit, startup and storage cleanup, OS update repair, and external port sanitization.',
                'device_category' => 'All Devices',
                'fixed_price' => 950.00,
                'estimated_turnaround_hours' => 1,
                'warranty_months' => 1,
                'inclusions' => [
                    'Startup bottleneck, background service & registry cleanup',
                    'Temporary cache purge & storage health verification',
                    'Corrupted system file & OS update repair',
                    'External connector & vent hygiene sanitization',
                ],
                'is_popular' => false,
            ],
            [
                'title' => 'Keyboard & Mouse Deep Cleaning & Maintenance',
                'description' => 'Complete keycap and shell sanitization, switch and scroll-wheel contact de-oxidation, and optical sensor tune-up for any keyboard or mouse.',
                'device_category' => 'Peripherals',
                'fixed_price' => 550.00,
                'estimated_turnaround_hours' => 1,
                'warranty_months' => 1,
                'inclusions' => [
                    'Full keycap & exterior chassis ultrasonic sanitization',
                    'Switch stem & scroll-wheel encoder contact cleaning',
                    'Optical sensor lens & PTFE glide pad inspection',
                    'Cable strain-relief & wireless receiver check',
                ],
                'is_popular' => false,
            ],
            [
                'title' => 'Printer Cartridge Refill & Printhead Maintenance',
                'description' => 'Flat-rate ink or toner refill, clogged printhead nozzle flush, paper-feed roller cleaning, and alignment calibration.',
                'device_category' => 'Printer',
                'fixed_price' => 650.00,
                'estimated_turnaround_hours' => 2,
                'warranty_months' => 1,
                'inclusions' => [
                    'Standard black or color ink / toner refill service',
                    'Ultrasonic clogged printhead & nozzle purge',
                    'Pickup & paper-feed rubber roller cleaning',
                    'Nozzle check & alignment test-page verification',
                ],
                'is_popular' => false,
            ],
            [
                'title' => 'Encrypted Data Backup & OS Recovery',
                'description' => 'Corrupted boot-sector repair and encrypted logical file migration onto your target storage medium.',
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
                'description' => 'Emergency board-level chemical decontamination bath and corrosion neutralization service.',
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
            [
                'title' => 'BIOS / UEFI Firmware Update & Recovery',
                'description' => 'Official system BIOS/UEFI firmware flashing, Secure Boot & TPM setup, and corrupted NVRAM recovery.',
                'device_category' => 'Laptop & PC',
                'fixed_price' => 800.00,
                'estimated_turnaround_hours' => 1,
                'warranty_months' => 3,
                'inclusions' => [
                    'Verified OEM BIOS / UEFI firmware flash & checksum check',
                    'TPM 2.0, Secure Boot & UEFI boot-order configuration',
                    'CMOS profile backup and NVRAM reset stabilization',
                    'Post-flash POST boot & thermal fan curve verification',
                ],
                'is_popular' => false,
            ],
            [
                'title' => 'SSD / HDD Cloning & Disk Partition Migration',
                'description' => 'Bit-for-bit OS, applications, and user partition cloning across drives with boot sector alignment and health scan.',
                'device_category' => 'Laptop & PC',
                'fixed_price' => 950.00,
                'estimated_turnaround_hours' => 2,
                'warranty_months' => 3,
                'inclusions' => [
                    'Bit-for-bit OS & partition migration across NVMe / SATA drives',
                    '4K GPT / MBR alignment and EFI bootloader repair',
                    'Pre-clone SMART sector health diagnostic scan',
                    'Post-migration boot verification and checksum validation',
                ],
                'is_popular' => true,
            ],
            [
                'title' => 'Dual-Boot Linux & Windows Environment Setup',
                'description' => 'Non-destructive storage partitioning and dual-boot Ubuntu, Fedora, or Pop!_OS installation alongside Windows.',
                'device_category' => 'Laptop & PC',
                'fixed_price' => 1100.00,
                'estimated_turnaround_hours' => 2,
                'warranty_months' => 1,
                'inclusions' => [
                    'Safe non-destructive Windows partition shrink & backup',
                    'Ubuntu / Fedora / Pop!_OS clean UEFI installation',
                    'GRUB / systemd-boot chooser & Wi-Fi/GPU driver setup',
                    'Shared exFAT / NTFS workspace mounting & rollback snapshot',
                ],
                'is_popular' => false,
            ],
            [
                'title' => 'Android / iOS Stock Firmware Reflash & Bootloop Fix',
                'description' => 'Official signed stock firmware restoration via Fastboot, Odin, or iOS DFU recovery to cure soft bricks and OS bootloops.',
                'device_category' => 'Smartphone',
                'fixed_price' => 750.00,
                'estimated_turnaround_hours' => 1,
                'warranty_months' => 1,
                'inclusions' => [
                    'Official signed stock ROM via Fastboot / Odin / iOS DFU',
                    'Soft-brick & recovery loop resolution without hardware teardown',
                    'System partition integrity check & Dalvik/ART cache rebuild',
                    'Baseband, Wi-Fi MAC & IMEI sanity verification',
                ],
                'is_popular' => true,
            ],
            [
                'title' => 'Cross-Device Phone Data Transfer & Cloud Sync',
                'description' => 'Guided encrypted migration of contacts, WhatsApp chats, photos, apps, and cloud keychains between any two phones.',
                'device_category' => 'Smartphone',
                'fixed_price' => 600.00,
                'estimated_turnaround_hours' => 1,
                'warranty_months' => 1,
                'inclusions' => [
                    'Full contacts, SMS, call log & media transfer',
                    'WhatsApp & messaging history encrypted backup migration',
                    'Cloud storage, iCloud & 2FA authenticator handoff assist',
                    'Verified media integrity & duplicate cleanup check',
                ],
                'is_popular' => false,
            ],
            [
                'title' => 'Speaker, Mic & Charging Port Ultrasonic Lint Clean',
                'description' => 'Microscopic debris, pocket lint, and oxidation extraction from USB-C/Lightning ports and muffled acoustic meshes.',
                'device_category' => 'Smartphone',
                'fixed_price' => 350.00,
                'estimated_turnaround_hours' => 1,
                'warranty_months' => 1,
                'inclusions' => [
                    'Microscope pocket-lint & oxidation removal from charging port',
                    'Earpiece, bottom speaker & mic acoustic mesh unblocking',
                    'ESD-safe isopropyl micro-brush & contact treatment',
                    'Fast-charging wattage & clear voice-recording verification',
                ],
                'is_popular' => true,
            ],
            [
                'title' => 'Gaming Console Thermal Clean & Fan Maintenance',
                'description' => 'Model-independent internal dust extraction, cooling heatsink cleaning, fan lubrication, and thermal compound renewal.',
                'device_category' => 'Gaming & Console',
                'fixed_price' => 1400.00,
                'estimated_turnaround_hours' => 2,
                'warranty_months' => 3,
                'inclusions' => [
                    'Internal dust extraction across console heatsink & PSU vents',
                    'High-conductivity thermal paste / PTM pad renewal',
                    'Cooling fan bearing clean & airflow acoustic balancing',
                    '30-minute sustained gameplay thermal telemetry verification',
                ],
                'is_popular' => false,
            ],
            [
                'title' => 'Controller Analog Drift Calibration & Micro-Clean',
                'description' => 'Sensor potentiometer ultrasonic contact clean, deadzone software calibration, and button membrane maintenance for gamepads.',
                'device_category' => 'Gaming & Console',
                'fixed_price' => 500.00,
                'estimated_turnaround_hours' => 1,
                'warranty_months' => 3,
                'inclusions' => [
                    'Analog module ultrasonic de-oxidation & carbon-track clean',
                    'Stick center-snap & software deadzone calibration',
                    'Bumper, trigger & face-button contact sanitization',
                    'Live circularity & input-latency gamepad verification',
                ],
                'is_popular' => false,
            ],
            [
                'title' => 'Wi-Fi Router Firmware, Mesh & DNS Setup',
                'description' => 'Flat-rate router/ONT firmware hardening, mesh node pairing, channel optimization, and encrypted DNS security setup.',
                'device_category' => 'Networking',
                'fixed_price' => 650.00,
                'estimated_turnaround_hours' => 1,
                'warranty_months' => 3,
                'inclusions' => [
                    'Official router / ONT firmware flash & admin hardening',
                    'Wi-Fi channel congestion scan & band-steering optimization',
                    'Mesh node pairing & WPA3 / encrypted DNS configuration',
                    'Throughput, bufferbloat & ping stability verification',
                ],
                'is_popular' => false,
            ],
            [
                'title' => 'Printer Paper Jam, Roller Clean & Driver Setup',
                'description' => 'Feed-path debris extraction, rubber pickup roller reconditioning, spooler reset, and multi-OS network driver setup.',
                'device_category' => 'Printer',
                'fixed_price' => 550.00,
                'estimated_turnaround_hours' => 1,
                'warranty_months' => 1,
                'inclusions' => [
                    'Pickup & separation roller rubber reconditioning',
                    'Paper-path jam sensor & encoder strip cleaning',
                    'Windows / macOS network spooler & driver reconfiguration',
                    'Multi-page duplex test print feed verification',
                ],
                'is_popular' => false,
            ],
            [
                'title' => 'Laptop Fan Noise Tuning & Vent Optimization',
                'description' => 'Cooling fan blade & intake exhaust precision cleaning paired with OS thermal-throttling audit and fan-curve tuning.',
                'device_category' => 'Laptop & PC',
                'fixed_price' => 650.00,
                'estimated_turnaround_hours' => 1,
                'warranty_months' => 3,
                'inclusions' => [
                    'Precision blower fan blade & vent fin dust clearance',
                    'Background CPU wake-lock & thermal-throttling audit',
                    'Balanced OS power-plan & silent fan-curve tuning',
                    'Idle acoustic dB & sustained load RPM verification',
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
            'Mechanical & Chiclet Keyboard Replacement' => 'Windows & OS Clean Installation',
            'Clean OS Installation & Drivers Setup' => 'Windows & OS Clean Installation',
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

        $this->syncSeededShopCapabilities($titleToId);

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

    /**
     * Attach specialty-aligned fixed-price packages to seeded workshops and sync VendorProfile flags.
     *
     * @param  array<string, int>  $titleToId
     */
    public function syncSeededShopCapabilities(array $titleToId = []): void
    {
        if (!Schema::hasTable('repair_shop_service_package')) {
            return;
        }
        if (empty($titleToId)) {
            $titleToId = ServicePackage::where('is_active', true)->pluck('id', 'title')->all();
        }
        if (empty($titleToId)) {
            return;
        }

        $seededProfiles = VendorProfile::query()
            ->where('notes', 'LIKE', 'group_key:%')
            ->get(['id', 'provides_fixed_packages', 'offered_package_ids'])
            ->keyBy('id');

        if ($seededProfiles->isEmpty()) {
            return;
        }

        $shops = RepairShop::query()
            ->whereIn('vendor_profile_id', $seededProfiles->keys()->all())
            ->get(['id', 'vendor_profile_id', 'name', 'slug', 'city', 'specialties']);

        if ($shops->isEmpty()) {
            return;
        }

        $now = now();
        $validIdSet = array_flip(array_values($titleToId));
        $pivotRows = [];
        $profilePackages = [];
        $unmodifiedShopIds = [];

        foreach ($shops as $shop) {
            $profile = $seededProfiles->get($shop->vendor_profile_id);
            if (!$profile || $profile->provides_fixed_packages === false) {
                continue;
            }

            if ($profile->provides_fixed_packages === true && is_array($profile->offered_package_ids)) {
                $pkgIds = array_values(array_filter(
                    array_map('intval', $profile->offered_package_ids),
                    fn (int $id) => isset($validIdSet[$id])
                ));
            } else {
                $pkgIds = $this->resolvePackagesForShop($shop, $titleToId);
                $unmodifiedShopIds[] = $shop->id;
            }

            foreach ($pkgIds as $pkgId) {
                $pivotRows[] = [
                    'repair_shop_id' => $shop->id,
                    'service_package_id' => $pkgId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $profilePackages[$profile->id][$pkgId] = true;
            }
        }

        if (!empty($unmodifiedShopIds)) {
            foreach (array_chunk($unmodifiedShopIds, 500) as $idChunk) {
                DB::table('repair_shop_service_package')
                    ->whereIn('repair_shop_id', $idChunk)
                    ->delete();
            }
        }

        foreach (array_chunk($pivotRows, 500) as $chunk) {
            DB::table('repair_shop_service_package')->upsert(
                $chunk,
                ['repair_shop_id', 'service_package_id'],
                ['updated_at']
            );
        }

        foreach ($profilePackages as $vpId => $idMap) {
            $profile = $seededProfiles->get($vpId);
            if ($profile && $profile->provides_fixed_packages === null) {
                VendorProfile::where('id', $vpId)->update([
                    'provides_fixed_packages' => true,
                    'offered_package_ids' => json_encode(array_values(array_keys($idMap))),
                ]);
            }
        }
    }

    /**
     * Resolve deterministic, specialty-aligned fixed packages so capability matching genuinely differentiates shops.
     *
     * @param  array<string, int>  $titleToId
     * @return int[]
     */
    private function resolvePackagesForShop(RepairShop $shop, array $titleToId): array
    {
        $specs = is_array($shop->specialties) ? $shop->specialties : [];
        $blob = strtolower(implode(' ', $specs) . ' ' . $shop->name . ' ' . $shop->slug);
        $hash = abs(crc32((string) $shop->slug));

        $doesLaptop = str_contains($blob, 'laptop') || str_contains($blob, 'macbook')
            || str_contains($blob, 'pc') || str_contains($blob, 'computer')
            || str_contains($blob, 'desktop') || str_contains($blob, 'keyboard')
            || str_contains($blob, 'ultrabook') || str_contains($blob, 'trackpad');
        $doesPhone = str_contains($blob, 'phone') || str_contains($blob, 'iphone')
            || str_contains($blob, 'android') || str_contains($blob, 'mobile')
            || str_contains($blob, 'samsung') || str_contains($blob, 'xiaomi')
            || str_contains($blob, 'display') || str_contains($blob, 'screen')
            || str_contains($blob, 'tablet');
        $doesConsole = str_contains($blob, 'console') || str_contains($blob, 'gaming')
            || str_contains($blob, 'ps5') || str_contains($blob, 'xbox')
            || str_contains($blob, 'nintendo');
        $doesPrinter = str_contains($blob, 'printer') || str_contains($blob, 'cartridge')
            || str_contains($blob, 'roller') || str_contains($blob, 'scanner')
            || str_contains($blob, 'toner');
        $doesMicroSolder = str_contains($blob, 'logic board') || str_contains($blob, 'motherboard')
            || str_contains($blob, 'micro') || str_contains($blob, 'water')
            || str_contains($blob, 'liquid') || str_contains($blob, 'bga');
        $doesNetwork = str_contains($blob, 'router') || str_contains($blob, 'network')
            || str_contains($blob, 'wifi') || str_contains($blob, 'computer')
            || str_contains($blob, 'it ') || str_contains($blob, 'tech');

        // Guarantee realistic baseline domain on generic OSM shop names without explicit hardware keywords.
        if (!$doesLaptop && !$doesPhone && !$doesConsole && !$doesPrinter) {
            if ($hash % 3 === 0) {
                $doesLaptop = true;
                $doesPhone = true;
            } elseif ($hash % 3 === 1) {
                $doesPhone = true;
            } else {
                $doesLaptop = true;
            }
        }

        $candidates = [
            'Virus, Malware & Adware Troubleshoot' => $doesLaptop || $doesPhone,
            'System Tune-Up & Preventive Maintenance' => $doesLaptop || $doesPhone,
            'Encrypted Data Backup & OS Recovery' => ($doesLaptop || $doesPhone) && ($hash % 3 !== 0),
            'Ultrasonic Liquid-Spill Chemical Cleaning' => $doesMicroSolder || ($hash % 5 === 0),

            'Android Rooting & Firmware Flash' => $doesPhone && ($hash % 2 === 0 || str_contains($blob, 'software') || str_contains($blob, 'android')),
            'Android / iOS Stock Firmware Reflash & Bootloop Fix' => $doesPhone && ($hash % 3 !== 1),
            'Cross-Device Phone Data Transfer & Cloud Sync' => $doesPhone,
            'Speaker, Mic & Charging Port Ultrasonic Lint Clean' => $doesPhone,

            'Windows & OS Clean Installation' => $doesLaptop,
            'Thermal Repasting & Internal Dust Deep Clean' => $doesLaptop && ($hash % 4 !== 1),
            'BIOS / UEFI Firmware Update & Recovery' => $doesLaptop && ($doesMicroSolder || $hash % 2 === 0),
            'SSD / HDD Cloning & Disk Partition Migration' => $doesLaptop && ($hash % 3 !== 2),
            'Dual-Boot Linux & Windows Environment Setup' => $doesLaptop && ($hash % 3 === 0 || str_contains($blob, 'linux') || str_contains($blob, 'dev')),
            'Laptop Fan Noise Tuning & Vent Optimization' => $doesLaptop,

            'Keyboard & Mouse Deep Cleaning & Maintenance' => $doesLaptop || str_contains($blob, 'keyboard') || str_contains($blob, 'peripheral'),

            'Gaming Console Thermal Clean & Fan Maintenance' => $doesConsole || ($doesLaptop && $doesMicroSolder && $hash % 3 === 0),
            'Controller Analog Drift Calibration & Micro-Clean' => $doesConsole || ($hash % 5 === 0),

            'Wi-Fi Router Firmware, Mesh & DNS Setup' => ($doesNetwork || $doesLaptop) && ($hash % 3 === 0 || str_contains($blob, 'router') || str_contains($blob, 'network')),

            'Printer Cartridge Refill & Printhead Maintenance' => $doesPrinter || ($hash % 6 === 0),
            'Printer Paper Jam, Roller Clean & Driver Setup' => $doesPrinter || ($hash % 5 === 0),
        ];

        $ids = [];
        foreach ($candidates as $title => $eligible) {
            if ($eligible && isset($titleToId[$title])) {
                $ids[] = $titleToId[$title];
            }
        }

        if (empty($ids)) {
            foreach (['System Tune-Up & Preventive Maintenance', 'Virus, Malware & Adware Troubleshoot'] as $fallbackTitle) {
                if (isset($titleToId[$fallbackTitle])) {
                    $ids[] = $titleToId[$fallbackTitle];
                }
            }
        }

        return $ids;
    }
}
