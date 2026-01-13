<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class MagDevice extends Model
{
    use SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];


    protected $fillable = [
     'name', 'time','mac','days','user_id','notes','package_id', 'enabled', 'pack', 'mag_device_id'
    ];

    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
