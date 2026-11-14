<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisputeNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispute_case_id',
        'author_id',
        'note',
        'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    public function disputeCase()
    {
        return $this->belongsTo(DisputeCase::class, 'dispute_case_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
