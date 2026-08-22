<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractInstallment extends Model
{
    protected $fillable = [
        'contract_id',
        'installment_number',
        'amount',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
}
