<?php

namespace App\Http\Controllers;

use App\Models\RepairRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Feature R13: FastAPI GROQ/Gemini AI Request Summary Microservice Client
 * Assigned Developer: Farhan Tanvir [22299179] | Sprint 2
 * MVC Layer: Controller / Service Client
 */
class AISummaryController extends Controller
{
    /**
     * Request an automated AI diagnostic summary for a customer device fault report.
     */
    public function generateSummary(Request $request, $requestId)
    {
        $repairRequest = RepairRequest::findOrFail($requestId);

        $aiMicroserviceUrl = env('AI_MICROSERVICE_URL', 'http://127.0.0.1:5000');

        try {
            $response = Http::timeout(15)->post("{$aiMicroserviceUrl}/api/ai/summarize-request", [
                'device_type' => $repairRequest->device_type,
                'brand' => $repairRequest->brand,
                'model_name' => $repairRequest->model_name,
                'issue_title' => $repairRequest->issue_title,
                'issue_description' => $repairRequest->issue_description,
            ]);

            if ($response->successful()) {
                $summaryData = $response->json();
                $generatedSummary = $summaryData['summary'] ?? 'Summary generated successfully.';

                $repairRequest->update([
                    'ai_diagnostic_summary' => $generatedSummary,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'AI Diagnostic summary generated via FastAPI microservice.',
                    'data' => [
                        'summary' => $generatedSummary,
                        'estimated_component_faults' => $summaryData['suspected_faults'] ?? [],
                    ],
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'AI Microservice returned error: ' . $response->status(),
            ], 502);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to connect to AI microservice: ' . $e->getMessage(),
            ], 503);
        }
    }
}
