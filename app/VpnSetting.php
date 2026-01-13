<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VpnSetting extends Model
{
    protected $fillable = [
        'host', 'protocol', 'port', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];
}
