<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature R14: Automated Multi-Channel BulkSMS & Email Notification Log
 * Assigned Developer: Farhan Tanvir [Student ID: 22299179]
 */
class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_request_id',
        'delivery_job_id',
        'channel', // 'SMS', 'EMAIL', 'PUSH', 'IN_APP'
        'recipient',
        'subject',
        'body',
        'provider',
        'provider_ref',
        'status', // 'QUEUED', 'SENT', 'FAILED'
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class, 'repair_request_id');
    }

    public function deliveryJob()
    {
        return $this->belongsTo(DeliveryJob::class, 'delivery_job_id');
    }
}
