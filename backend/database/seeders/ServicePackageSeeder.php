<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServicePackage;

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
                $pkg
            );
        }
    }
}
