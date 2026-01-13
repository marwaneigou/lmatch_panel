<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class MultiCode extends Model
{
    use SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];


    protected $fillable = [
        'len','name', 'number', 'time','mac','package_id','days','max','notes','user_id','enabled', 'pack'
    ];

    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
