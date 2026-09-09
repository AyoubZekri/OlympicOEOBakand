<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractReview extends Model
{
    use HasFactory;

    protected $table = 'contract_reviews';
    protected $guarded = ['id'];

    protected $casts = [
        'requires_official_avenant' => 'boolean',
        'player_signature' => 'boolean',
        'meeting_date' => 'datetime',
    ];

    public function playerId()
    {
        return $this->belongsTo(Individual::class, 'player_id');
    }

    public function evaluationId()
    {
        return $this->belongsTo(PlayerEvaluation::class, 'evaluation_id');
    }

    public function clubRepresentativeId()
    {
        return $this->belongsTo(Individual::class, 'club_representative_id');
    }
}
