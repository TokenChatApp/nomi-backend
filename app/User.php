<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    protected $primaryKey = 'user_id';

    const CREATED_AT = 'user_date_added';
    const UPDATED_AT = 'user_date_updated';

    public function bookings()
    {
        return $this->hasMany('App\Booking', 'requestor_id', 'user_id');
    }

    public function requests()
    {
        return $this->hasMany('App\BookingItem', 'user_id', 'user_id');
    }
}