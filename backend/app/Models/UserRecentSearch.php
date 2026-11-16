<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRecentSearch extends Model
{
    protected $table = 'user_recent_searches';

    protected $fillable = [
        'user_id',
        'query',
        'normalized_query',
        'target_section',
        'search_count',
        'last_searched_at',
    ];

    protected $casts = [
        'search_count' => 'integer',
        'last_searched_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
