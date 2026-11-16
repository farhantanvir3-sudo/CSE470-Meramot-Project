<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherRedemption extends Model
{
    use HasFactory;

    protected $table = 'voucher_redemptions';

    protected $fillable = [
        'voucher_id',
        'user_id',
        'shop_id',
        'repair_request_id',
        'voucher_code',
        'discount_amount',
        'redeemed_at',
    ];

    protected $casts = [
        'voucher_id' => 'integer',
        'user_id' => 'integer',
        'shop_id' => 'integer',
        'repair_request_id' => 'integer',
        'discount_amount' => 'decimal:2',
        'redeemed_at' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

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
}
