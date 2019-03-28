<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Verification extends Model
{
	protected $table = 'verifications';

    protected $primaryKey = 'verification_id';
    protected $hidden = array('verification_id', 'verification_date_added', 'verification_date_updated');

    const CREATED_AT = 'verification_date_added';
    const UPDATED_AT = 'verification_date_updated';

    public function user()
    {
        return $this->belongsTo('App\User', 'verification_user_id', 'user_id');
    }
}