<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature R13: FastAPI GROQ/Gemini AI Request Summary Microservice Log
 * Assigned Developer: Farhan Tanvir [Student ID: 22299179]
 */
class AISummaryLog extends Model
{
    use HasFactory;

    protected $table = 'ai_summary_logs';

    protected $fillable = [
        'repair_request_id',
        'triggered_by_user_id',
        'provider',
        'model_name',
        'summary',
        'suspected_faults',
        'suggested_parts',
        'risk_level',
        'estimated_bench_hours',
        'estimated_cost_min',
        'estimated_cost_max',
        'latency_ms',
    ];

    protected $casts = [
        'suspected_faults' => 'array',
        'suggested_parts' => 'array',
        'estimated_bench_hours' => 'decimal:2',
        'estimated_cost_min' => 'decimal:2',
        'estimated_cost_max' => 'decimal:2',
        'latency_ms' => 'integer',
    ];

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class);
    }

    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
