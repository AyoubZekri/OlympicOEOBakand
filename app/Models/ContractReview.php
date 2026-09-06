<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractReview extends Model
{
    use HasFactory;

    protected $table = 'contract_reviews';
    protected $guarded = ['id'];

    public function playerId()
    {
        return $this->belongsTo(Individual::class, 'player_id');
    }

    public function evaluationId()
    {
        return $this->belongsTo(Evaluation::class, 'evaluation_id');
    }

    public function clubRepresentativeId()
    {
        return $this->belongsTo(Individual::class, 'club_representative_id');
    }
}
