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
        // PostgreSQL migration columns (`payment_transactions`)
        'user_id',
        'repair_request_id',
        'invoice_id',
        'amount',
        'currency',
        'payment_method', // 'BKASH', 'NAGAD', 'CARD', 'CASH', 'SSLCOMMERZ'
        'status',         // 'PENDING', 'AUTHORIZED', 'PAID', 'FAILED', 'REFUNDED', 'PARTIALLY_REFUNDED'
        'transaction_reference',
        'gateway_response',
        'paid_at',
        // Legacy compatibility aliases
        'transaction_id',
        'customer_id',
        'gateway',
        'payment_status',
        'bank_trx_id',
        'card_type',
        'invoice_number',
        'invoice_pdf_path',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function setCustomerIdAttribute($value): void
    {
        $this->attributes['user_id'] = $value;
    }

    public function getCustomerIdAttribute()
    {
        return $this->attributes['user_id'] ?? null;
    }

    public function setTransactionIdAttribute($value): void
    {
        $this->attributes['transaction_reference'] = $value;
    }

    public function getTransactionIdAttribute()
    {
        return $this->attributes['transaction_reference'] ?? null;
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class);
    }
}
