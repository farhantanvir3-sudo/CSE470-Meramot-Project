<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_id',
        'repair_request_id',
        'repair_job_id',
        'assigned_admin_id',
        'subject',
        'message',
        'status', // 'OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED', 'ESCALATED'
        'priority',
        'admin_notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shop()
    {
        return $this->belongsTo(RepairShop::class, 'shop_id');
    }

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class, 'repair_request_id');
    }

    public function repairJob()
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function messages()
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id');
    }
}
