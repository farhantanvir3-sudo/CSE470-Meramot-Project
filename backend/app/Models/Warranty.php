<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warranty extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_job_id',
        'title',
        'details',
        'duration_days',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function repairJob()
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }
}
