<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class BookingItem extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

	protected $table = 'requests_items';

    protected $primaryKey = 'item_id';

    const CREATED_AT = 'item_date_added';
    const UPDATED_AT = 'item_date_updated';

    public function booking()
    {
        return $this->belongsTo('App\Booking', 'request_id', 'request_id');
    }

    public function request_user()
    {
        return $this->belongsTo('App\User', 'user_id', 'user_id');
    }
}