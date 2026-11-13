<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * In-App Notification Bell Pop-Up Alert Model.
 */
class UserNotification extends Model
{
    use HasFactory;

    protected $table = 'user_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'severity',
        'title',
        'message',
        'action_url',
        'is_read',
        'popup_shown_at',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'popup_shown_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
