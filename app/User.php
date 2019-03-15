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
    protected $hidden = array('email', 'password', 'mobile_no', 'referral', 'token', 'status', 'user_date_added', 
                              'user_date_updated', 'last_logged_in', 'user_action', 'user_notified');

    const CREATED_AT = 'user_date_added';
    const UPDATED_AT = 'user_date_updated';

    public function bookings()
    {
        return $this->hasMany('App\Booking', 'requestor_id', 'user_id');
    }

    public function booking()
    {
        return $this->belongsTo('App\BookingItem', 'user_id', 'user_id');
    }
}