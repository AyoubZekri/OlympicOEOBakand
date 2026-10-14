<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A member's suggestion, complaint or note, and the management's reply (and rating, for a suggestion) */
class MemberFeedback extends Model
{
    protected $table = 'member_feedback';

    public const TYPES = ['suggestion', 'complaint', 'note'];

    protected $fillable = [
        'user_id', 'individual_id', 'type', 'subject', 'body',
        'reply', 'rating', 'replied_by', 'replied_at', 'reply_seen_at', 'read_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'replied_at' => 'datetime',
        'reply_seen_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function individual()
    {
        return $this->belongsTo(Individual::class, 'individual_id');
    }

    public function replier()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
