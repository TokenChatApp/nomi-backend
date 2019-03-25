<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class Photo extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    protected $table = 'users_photos';
    protected $primaryKey = 'photo_id';
    protected $hidden = array('photo_user_id', 'photo_date_added', 'photo_date_updated');

    const CREATED_AT = 'photo_date_added';
    const UPDATED_AT = 'photo_date_updated';

    public function user()
    {
        return $this->belongsTo('App\User', 'photo_user_id', 'user_id');
    }
}