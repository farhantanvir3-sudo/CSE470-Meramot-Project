<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature R17: Online Payment Gateway & PDF Invoice
 * Assigned Developer: Md. Nawshad Zaman Khan [Student ID: 23301622]
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_request_id',
        'repair_job_id',
        'shop_id',
        'type', // 'CHECKUP', 'REPAIR', 'DELIVERY', 'FINAL'
        'status', // 'DRAFT', 'ISSUED', 'PARTIALLY_PAID', 'PAID', 'VOID', 'REFUNDED'
        'invoice_number',
        'subtotal_amount',
        'delivery_amount',
        'discount_amount',
        'commission_amount',
        'total_amount',
        'issued_at',
        'due_at',
        'paid_at',
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'delivery_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class, 'repair_request_id');
    }

    public function repairJob()
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function shop()
    {
        return $this->belongsTo(RepairShop::class, 'shop_id');
    }

    public function lineItems()
    {
        return $this->hasMany(InvoiceLineItem::class, 'invoice_id');
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class, 'invoice_id');
    }
}
