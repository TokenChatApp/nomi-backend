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

    public function request_user()
    {
        return $this->belongsTo('App\User', 'requestor_id');
    }
}