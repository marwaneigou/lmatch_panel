<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Channel extends Model
{
    use SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];


    protected $fillable = [
        'category_id', 'title', 'url','lien_image','upload_image','featured','status','notes'
    ];


    public function category()
    {
        return $this->hasOne('App\Category');
    }
}
