<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Vpn extends Model
{
    protected $fillable = [
        'username', 'password', 'user_id', 'vpn_host_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vpnHost()
    {
        return $this->belongsTo(VpnSetting::class, 'vpn_host_id');
    }
}
