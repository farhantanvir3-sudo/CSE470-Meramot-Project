<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature R17: SSLCommerz/Stripe Online Payment Gateway & PDF Invoice
 * Assigned Developer: Md. Nawshad Zaman Khan [23301622]
 */
class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'repair_request_id',
        'customer_id',
        'amount',
        'gateway', // 'sslcommerz', 'stripe'
        'payment_status', // 'pending', 'successful', 'failed', 'refunded'
        'bank_trx_id',
        'card_type',
        'invoice_number',
        'invoice_pdf_path',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class);
    }
}
