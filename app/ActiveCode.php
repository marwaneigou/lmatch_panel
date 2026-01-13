<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ActiveCode extends Model
{
    use SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];


    protected $fillable = [
        'len','name', 'number', 'time','mac','days','duration','type','user_id','notes','package_id', 'enabled', 'pack'
    ];

    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
