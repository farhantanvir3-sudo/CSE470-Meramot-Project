<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EscrowLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'repair_request_id',
        'customer_user_id',
        'vendor_user_id',
        'amount',
        'gross_amount',
        'platform_commission_amount',
        'vendor_net_amount',
        'action', // 'HOLD', 'RELEASE', 'REFUND'
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'platform_commission_amount' => 'decimal:2',
        'vendor_net_amount' => 'decimal:2',
    ];

    public function payment()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_id');
    }

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class, 'repair_request_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }
}
