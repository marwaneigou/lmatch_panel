<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionLog extends Model
{
    protected $table = 'transaction_logs';

    protected $fillable = [
        'reseller_id',
        'target_id',
        'target_type',
        'action',
        'amount',
        'old_balance',
        'new_balance',
        'details',
        'ip',
    ];

    public function reseller()
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }
}
