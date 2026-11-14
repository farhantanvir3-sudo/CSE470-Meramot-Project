<?php

namespace App\Services;

use App\Models\AISummaryLog;
use App\Models\RepairRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * FEATURE R13: AI Hardware Fault Triage & Request Summary Engine
 * ============================================================================
 * Assigned Developer: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Connects Laravel 10 MVC to the standalone Python FastAPI AI Microservice
 * (`/api/ai/summarize-request`, Groq / Gemini + Heuristic Triage), with a
 * built-in deterministic hardware diagnostic analyzer so R13 remains 100%
 * operational even if FastAPI port 5000 is offline or running without LLM keys.
 * ============================================================================
 */
class AISummaryService
{
    public function summarize(RepairRequest $repairRequest, ?int $triggeredByUserId = null, bool $preferRemote = true): array
    {
        $startedAt = microtime(true);
        $issueTitle = (string) ($repairRequest->title ?: $repairRequest->issue_category ?: 'Hardware Diagnostic Request');
        $issueDescription = trim(implode(' — ', array_filter([
            $repairRequest->problem,
            $repairRequest->description,
            $repairRequest->issue_category,
        ])));

        $payload = [
            'device_type' => (string) ($repairRequest->device_type ?: 'Electronic Device'),
            'brand' => (string) ($repairRequest->brand ?: 'Generic'),
            'model_name' => (string) ($repairRequest->model_name ?: 'Model'),
            'issue_title' => $issueTitle,
            'issue_description' => $issueDescription !== '' ? $issueDescription : $issueTitle,
            'issue_category' => (string) ($repairRequest->issue_category ?: ''),
        ];

        $result = null;

        if ($preferRemote && !app()->environment('testing')) {
            $result = $this->tryRemoteFastApi($payload);
        } elseif ($preferRemote && app()->environment('testing')) {
            // In PHPUnit, allow Http::fake() responses when configured, otherwise fall back cleanly
            $result = $this->tryRemoteFastApi($payload, 1.5);
        }

        if ($result === null) {
            $result = $this->localHardwareTriage($payload);
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $result['latency_ms'] = $latencyMs;
        $result['generated_at'] = now()->toIso8601String();

        $updates = [
            'ai_summary' => $result['summary'],
        ];
        if (Schema::hasColumn('repair_requests', 'ai_triage_json')) {
            $updates['ai_triage_json'] = [
                'provider' => $result['provider'],
                'model_name' => $result['model_name'],
                'suspected_faults' => $result['suspected_faults'],
                'suggested_parts' => $result['suggested_parts'],
                'risk_level' => $result['risk_level'],
                'estimated_bench_hours' => $result['estimated_bench_time_hours'],
                'estimated_cost_min' => $result['estimated_cost_min'],
                'estimated_cost_max' => $result['estimated_cost_max'],
                'latency_ms' => $latencyMs,
                'generated_at' => $result['generated_at'],
            ];
        }
        $repairRequest->update($updates);

        if (Schema::hasTable('ai_summary_logs')) {
            AISummaryLog::create([
                'repair_request_id' => $repairRequest->id,
                'triggered_by_user_id' => $triggeredByUserId,
                'provider' => $result['provider'],
                'model_name' => $result['model_name'],
                'summary' => $result['summary'],
                'suspected_faults' => $result['suspected_faults'],
                'suggested_parts' => $result['suggested_parts'],
                'risk_level' => $result['risk_level'],
                'estimated_bench_hours' => $result['estimated_bench_time_hours'],
                'estimated_cost_min' => $result['estimated_cost_min'],
                'estimated_cost_max' => $result['estimated_cost_max'],
                'latency_ms' => $latencyMs,
            ]);
        }

        return $result;
    }

    private function tryRemoteFastApi(array $payload, ?float $timeoutOverride = null): ?array
    {
        $baseUrl = rtrim((string) config('services.ai_microservice.url', env('AI_MICROSERVICE_URL', 'http://127.0.0.1:5000')), '/');
        if ($baseUrl === '') {
            return null;
        }

        $timeout = $timeoutOverride ?? (float) config('services.ai_microservice.timeout', 3.5);

        try {
            $response = Http::timeout($timeout)->acceptJson()->post("{$baseUrl}/api/ai/summarize-request", $payload);
            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            if (!is_array($data) || empty($data['summary'])) {
                return null;
            }

            return [
                'provider' => (string) ($data['provider'] ?? 'fastapi-microservice'),
                'model_name' => (string) ($data['model_name'] ?? 'meramot-fastapi-v2'),
                'summary' => (string) $data['summary'],
                'suspected_faults' => array_values((array) ($data['suspected_faults'] ?? ['Hardware fault requires bench verification'])),
                'suggested_parts' => array_values((array) ($data['suggested_parts'] ?? ['Bench diagnostic inspection'])),
                'risk_level' => strtoupper((string) ($data['risk_level'] ?? 'MEDIUM')),
                'estimated_bench_time_hours' => (float) ($data['estimated_bench_time_hours'] ?? 2.0),
                'estimated_cost_min' => (float) ($data['estimated_cost_min'] ?? 1000),
                'estimated_cost_max' => (float) ($data['estimated_cost_max'] ?? 3800),
            ];
        } catch (\Throwable $e) {
            Log::info('[AISummaryService] FastAPI microservice unreachable, engaging built-in hardware triage fallback: ' . $e->getMessage());
            return null;
        }
    }

    public function localHardwareTriage(array $payload): array
    {
        $haystack = mb_strtolower(implode(' ', [
            $payload['issue_title'] ?? '',
            $payload['issue_description'] ?? '',
            $payload['issue_category'] ?? '',
            $payload['device_type'] ?? '',
        ]));

        $rules = [
            [
                'keys' => ['screen', 'display', 'oled', 'lcd', 'crack', 'shatter', 'flicker', 'green line', 'touch', 'digitizer', 'black screen', 'panel'],
                'fault' => 'Display Panel / Digitizer Flex or Backlight IC Degradation',
                'part' => 'OEM OLED/IPS Display & Digitizer Assembly + Frame Seal',
                'hours' => 2.0,
                'risk' => 'MEDIUM',
                'min' => 2200,
                'max' => 8500,
            ],
            [
                'keys' => ['battery', 'drain', 'swollen', 'bulge', 'shut down', 'percentage', 'backup'],
                'fault' => 'Lithium-Polymer Cell Cycle Exhaustion / High Internal Impedance',
                'part' => 'Zero-Cycle Replacement Battery Pack + BMS Calibration',
                'hours' => 1.2,
                'risk' => 'LOW',
                'min' => 1200,
                'max' => 4200,
            ],
            [
                'keys' => ['charg', 'usb', 'type-c', 'lightning', 'not charging', 'slow charg', 'dc jack', 'adapter'],
                'fault' => 'Charging Daughterboard Wear / USB-C PD Controller (PMIC) Fault',
                'part' => 'Charging Port Flex Assembly / USB-C PD Controller IC',
                'hours' => 1.8,
                'risk' => 'MEDIUM',
                'min' => 950,
                'max' => 3200,
            ],
            [
                'keys' => ['water', 'liquid', 'rain', 'spill', 'tea', 'coffee', 'corrosion', 'wet', 'moisture'],
                'fault' => 'Multi-Rail PCB Liquid Ingress, Trace Oxidation & Electrolytic Short',
                'part' => 'Ultrasonic Isopropyl Treatment + SMD Passives Rework',
                'hours' => 4.0,
                'risk' => 'HIGH',
                'min' => 2500,
                'max' => 7500,
            ],
            [
                'keys' => ['dead', 'no power', 'won\'t turn on', 'not turning on', 'short', 'mosfet', 'motherboard', 'logic board', 'vrm', 'ic'],
                'fault' => 'Primary Power Rail (Vcore / PPVDD_MAIN) Short or PMIC Failure',
                'part' => 'DC-DC Buck MOSFET / Power Management IC + Micro-Soldering',
                'hours' => 4.5,
                'risk' => 'HIGH',
                'min' => 3000,
                'max' => 9500,
            ],
            [
                'keys' => ['overheat', 'thermal', 'fan', 'loud', 'throttl', 'hot', 'cooling'],
                'fault' => 'Heatsink Fin Obstruction, Dried Thermal Paste, or Fan Bearing Wear',
                'part' => 'High-Conductivity Thermal Paste/Pads + Cooling Fan Assembly',
                'hours' => 1.5,
                'risk' => 'LOW',
                'min' => 800,
                'max' => 2600,
            ],
            [
                'keys' => ['keyboard', 'key ', 'keys', 'trackpad', 'touchpad', 'typing'],
                'fault' => 'Keyboard Matrix Switch Oxidation / Trackpad Flex Ribbon Fault',
                'part' => 'Scissor-Switch Keyboard Assembly + Trackpad Ribbon Cable',
                'hours' => 2.2,
                'risk' => 'MEDIUM',
                'min' => 1800,
                'max' => 5200,
            ],
            [
                'keys' => ['hinge', 'frame', 'back glass', 'housing', 'lid', 'bezel'],
                'fault' => 'Chassis Standoff Fracture & Torque Hinge Seizure',
                'part' => 'Left/Right Torque Hinge Pair + Brass Insert Reinforcement',
                'hours' => 2.5,
                'risk' => 'MEDIUM',
                'min' => 1600,
                'max' => 4500,
            ],
            [
                'keys' => ['speaker', 'mic', 'audio', 'sound', 'earpiece', 'no voice', 'crackl'],
                'fault' => 'Acoustic Speaker Diaphragm Tear or Audio Codec IC Cold Joint',
                'part' => 'Loudspeaker/Earpiece Module + MEMS Microphone Flex',
                'hours' => 1.5,
                'risk' => 'LOW',
                'min' => 850,
                'max' => 2900,
            ],
            [
                'keys' => ['camera', 'blur', 'focus', 'lens', 'face id', 'ois'],
                'fault' => 'Camera OIS Actuator Fault / Cracked Optical Lens Cover',
                'part' => 'Camera Sensor Module + Sapphire Optical Lens Glass',
                'hours' => 1.5,
                'risk' => 'MEDIUM',
                'min' => 1500,
                'max' => 6200,
            ],
            [
                'keys' => ['ssd', 'hard drive', 'boot', 'bios', 'bsod', 'stuck on logo', 'bootloop', 'storage'],
                'fault' => 'NAND Storage Bad Blocks / SPI BIOS Firmware Corruption',
                'part' => 'NVMe SSD Module / SPI BIOS Chip Re-Flash & OS Recovery',
                'hours' => 2.5,
                'risk' => 'MEDIUM',
                'min' => 1200,
                'max' => 5800,
            ],
            [
                'keys' => ['hdmi', 'no signal', 'console', 'ps5', 'xbox', 'gpu', 'artifact', 'controller', 'stick drift'],
                'fault' => 'HDMI 2.1 Port Pin Shear / Hall-Effect Analog Sensor or Retimer IC Fault',
                'part' => 'HDMI 2.1 Socket / Hall-Effect Joystick Module + Retimer IC',
                'hours' => 2.8,
                'risk' => 'HIGH',
                'min' => 1800,
                'max' => 6500,
            ],
        ];

        $suspected = [];
        $parts = [];
        $maxHours = 0.0;
        $minSum = 0.0;
        $maxSum = 0.0;
        $riskOrder = ['LOW' => 1, 'MEDIUM' => 2, 'HIGH' => 3];
        $highestRisk = 'LOW';

        foreach ($rules as $rule) {
            foreach ($rule['keys'] as $kw) {
                if (str_contains($haystack, $kw)) {
                    $suspected[] = $rule['fault'];
                    $parts[] = $rule['part'];
                    $maxHours = max($maxHours, $rule['hours']);
                    $minSum += $rule['min'];
                    $maxSum += $rule['max'];
                    if ($riskOrder[$rule['risk']] > $riskOrder[$highestRisk]) {
                        $highestRisk = $rule['risk'];
                    }
                    break;
                }
            }
        }

        if (empty($suspected)) {
            $suspected = ['Intermittent Board-Level Component Fatigue / Connector Oxidation'];
            $parts = ['Multimeter & Thermal Imaging Bench Inspection + Flex Reseat'];
            $benchHours = 2.0;
            $highestRisk = 'MEDIUM';
            $costMin = 800.0;
            $costMax = 3000.0;
        } else {
            $benchHours = round(min($maxHours + (count($suspected) - 1) * 0.5, 8.0), 1);
            $costMin = round($minSum * 0.85, -1);
            $costMax = round($maxSum, -1);
        }

        $brand = trim((string) ($payload['brand'] ?? 'Device'));
        $model = trim((string) ($payload['model_name'] ?? ''));
        $device = trim((string) ($payload['device_type'] ?? 'Electronic Device'));
        $title = trim((string) ($payload['issue_title'] ?? 'Hardware Fault'));

        $summary = sprintf(
            '%s %s (%s) exhibiting "%s". Automated hardware triage identifies %s. Estimated bench window: ~%s hrs (%s complexity; typical estimate ৳%s–৳%s).',
            $brand,
            $model,
            $device,
            $title,
            implode('; ', array_slice($suspected, 0, 2)),
            number_format($benchHours, 1),
            $highestRisk,
            number_format($costMin, 0),
            number_format($costMax, 0)
        );

        return [
            'provider' => 'meramot-triage-engine',
            'model_name' => 'hardware-heuristic-v2',
            'summary' => $summary,
            'suspected_faults' => $suspected,
            'suggested_parts' => $parts,
            'risk_level' => $highestRisk,
            'estimated_bench_time_hours' => $benchHours,
            'estimated_cost_min' => $costMin,
            'estimated_cost_max' => $costMax,
        ];
    }
}
