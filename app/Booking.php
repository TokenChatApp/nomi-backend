<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class Booking extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

	protected $table = 'requests';

    protected $primaryKey = 'request_id';

    const CREATED_AT = 'request_date_added';
    const UPDATED_AT = 'request_date_updated';

    public function items()
    {
        return $this->hasMany('App\BookingItem', 'request_id', 'request_id');
    }

    public function users()
    {
        return $this->hasManyThrough('App\User', 'App\BookingItem', 'request_id', 'user_id', 'request_id', 'user_id')
                    ->select('is_accepted', 'is_selected', 'url', 'avatar', 'display_name', 'username', 'age', 'rate_level', 'rate_per_session', 'height', 'weight', 'language', 'nationality');
    }

    public function requestor()
    {
        return $this->belongsTo('App\User', 'requestor_id');
    }
}