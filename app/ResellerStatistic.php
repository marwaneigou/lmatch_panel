<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ResellerStatistic extends Model
{
    protected $fillable = [
        'reseller_id','solde', 'operation', 'operation_name', 'slug', 'admin_id'
    ];
}
