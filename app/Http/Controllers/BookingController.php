<?php

namespace App\Http\Controllers;

require '../vendor/autoload.php';

use App\Settings;
use App\Booking;
use App\BookingItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;

class BookingController extends Controller
{
    public function __construct()
    {

    }

    public function init()
    {
        return response()->make("", 204)
                         ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN
                        ]);;
    }

    public function create(Request $request)
    {
        $user = $request->auth;

        $validator = Validator::make($request->all(), [
           'request_date' => 'required',
           'profile_ids' => 'required'
        ]);

        $strings = explode(" ", $request_date);

        if ($validator->fails() || sizeof($strings) != 2) {
            return response()->make(array('status' => false, 'errorMessage' => 'Unable to create booking.',
                                          'errors' => $validator->messages(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }

        // create booking record
        $booking = new Booking;
        $booking->requestor_id = $user->user_id;
        $booking->request_date = $strings[0];
        $booking->request_start_time = $strings[1];
        $booking->request_end_time = date('H:i', strtotime('+2 hours'))$request->request_end_time;
        $booking->request_total_fee = 0;
        $booking->save();

        $booking_id = $booking->request_id;

        // create booking details
        $profileIds = explode(',', $request->profile_ids);
        if ($profileIds != NULL) {
            foreach ($profileIds as $id) {
                $item = new BookingItem;
                $item->user_id = $id;
                $item->request_id = $booking_id;
                $item->is_accepted = 0;
                $item->is_selected = 0;
                $item->save();
            }
        }
        
        return response()->make(array('status' => true, 'message' => 'Booking created successfully', 'session' => true), 200)
                         ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN,
                            'x-csrf-token' => $request->get('x-csrf-token')
                        ]);
    }
   
   public function show(Request $request, $id)
    {
        $user = $request->auth;
        $booking = Booking::with('users')
                        ->where('request_id', $id)
                        ->first();

        if ($booking->requestor_id == $user->user_id) {
            return response()->json($booking)
                            ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN,
                                'x-csrf-token' => $request->get('x-csrf-token')
                            ]);
        }
        else {
            return response()->make(array('status' => false, 'errorMessage' => 'Unable to view booking. You are not authorized.',
                                          'errors' => array(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }
    }

    public function search(Request $request)
    {
        $user = $request->auth;
        if ($user->gender == 'M') {
            $bookings = Booking::select('*')
                            ->with('users')
                            ->where('requestor_id', $user->user_id)
                            ->get();

            if ($bookings != null) {
                foreach ($bookings as $b) {
                    if ($b->is_confirmed == 0 && (strtotime($b->request_date) < time() || 
                        (strtotime($b->request_date) == strtotime(date('Y-m-d')) && strtotime($b->request_start_time) > strtotime(date('H:i:s'))))) {
                        $b->status = 'Expired';
                    }
                    else if ($b->is_confirmed == 1 && $b->is_paid == 1 && strtotime($b->request_date) == strtotime(date('Y-m-d')) &&
                             strtotime($b->request_start_time) <= strtotime(date('H:i:s')) && strtotime($b->request_end_time) >= strtotime(date('H:i:s'))) {
                        $b->status = 'Ongoing';
                    }
                    else if ($b->is_confirmed == 1 && $b->is_paid == 1 && (strtotime($b->request_date) < strtotime(date('Y-m-d')) || 
                             (strtotime($b->request_date) == strtotime(date('Y-m-d')) && strtotime($b->request_end_time) < strtotime(date('H:i:s'))))) {
                        $b->status = 'Completed';
                    }
                    else if ($b->is_confirmed == 0 && (strtotime($b->request_date) > strtotime(date('Y-m-d')) ||
                             (strtotime($b->request_date) == strtotime(date('Y-m-d')) && strtotime($b->request_start_time) > strtotime(date('H:i:s'))))) {
                        $b->status = 'Pending';
                    }
                    else if ($b->is_confirmed == 1 && $b->is_paid == 1 && (strtotime($b->request_date) > strtotime(date('Y-m-d')) ||
                             (strtotime($b->request_date) == strtotime(date('Y-m-d')) && strtotime($b->request_start_time) > strtotime(date('H:i:s'))))) {
                        $b->status = 'Confirmed';
                    }
                    else {
                        $b->status = 'Unknown';
                    }
                }
            }
        }
        else if ($user->gender == 'F') {
            $bookings = Booking::select('*')
                            ->with(['users' => function($query) use ($user) {
                                $query->where('requests_items.user_id', $user->user_id);
                            }])
                            ->get();

            if ($bookings != null) {
                foreach ($bookings as $b) {
                    if ($b->is_confirmed == 0 && (strtotime($b->request_date) < time() || 
                        (strtotime($b->request_date) == strtotime(date('Y-m-d')) && strtotime($b->request_start_time) > strtotime(date('H:i:s'))))) {
                        $b->status = 'Expired';
                    }
                    else if ($b->is_confirmed == 1 && $b->is_paid == 1 && strtotime($b->request_date) == strtotime(date('Y-m-d')) &&
                             strtotime($b->request_start_time) <= strtotime(date('H:i:s')) && strtotime($b->request_end_time) >= strtotime(date('H:i:s'))) {
                        $b->status = 'Ongoing';
                    }
                    else if ($b->is_confirmed == 1 && $b->is_paid == 1 && (strtotime($b->request_date) < strtotime(date('Y-m-d')) || 
                             (strtotime($b->request_date) == strtotime(date('Y-m-d')) && strtotime($b->request_end_time) < strtotime(date('H:i:s'))))) {
                        $b->status = 'Completed';
                    }
                    else if ($b->is_confirmed == 0 && (strtotime($b->request_date) > strtotime(date('Y-m-d')) ||
                             (strtotime($b->request_date) == strtotime(date('Y-m-d')) && strtotime($b->request_start_time) > strtotime(date('H:i:s'))))) {
                        $b->status = 'Pending';
                    }
                    else if ($b->is_confirmed == 1 && $b->is_paid == 1 && (strtotime($b->request_date) > strtotime(date('Y-m-d')) ||
                             (strtotime($b->request_date) == strtotime(date('Y-m-d')) && strtotime($b->request_start_time) > strtotime(date('H:i:s'))))) {
                        $b->status = 'Confirmed';
                    }
                    else {
                        $b->status = 'Unknown';
                    }
                }
            }
        }
        
        return response()->json($bookings)
                        ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN,
                            'x-csrf-token' => $request->get('x-csrf-token')
                        ]);
    }
   
    public function accept(Request $request)
    { 
        $user = $request->auth;
        $booking = BookingItem::with('booking')
                              ->where('request_id', $request->request_id)
                              ->where('user_id', $user->user_id)
                              ->first();

        if ($booking != null) {
            $booking->is_accepted = $request->accepted;
            $booking->save();
            
            return response()->make(array('status' => true, 'message' => 'Booking updated successfully', 'session' => true), 200)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }
        else {
            return response()->make(array('status' => false, 'errorMessage' => 'Unable to find booking.',
                                          'errors' => array(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }
    }

    public function confirm(Request $request)
    { 
        $user = $request->auth;
        $booking = Booking::find($request->request_id);

        if ($booking != null) {
            $booking->is_confirmed = 1;
            $booking->is_paid = 1;
            $booking->save();

            $profileIds = explode(',', $request->profile_ids);
            if ($profileIds != NULL) {
                foreach ($profileIds as $id) {
                    $item = BookingItem::with('booking')
                                        ->where('request_id', $request->request_id)
                                        ->where('user_id', $id)
                                        ->first();
                    $item->is_selected = 1;
                    $item->save();
                }
            }
            
            return response()->make(array('status' => true, 'message' => 'Booking confirmed successfully', 'session' => true), 200)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }
        else {
            return response()->make(array('status' => false, 'errorMessage' => 'Unable to find booking.',
                                          'errors' => array(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }
    }

    /*
    public function search(Request $request, $type)
    {
        $user = $request->auth;
        $bookings = array();

        if ($type == 1) {
            $bookings = Booking::select('*')
                        ->with('users')
                        ->where('is_confirmed', 0)
                        ->where(function($query) {
                            $query->where('request_date', '<', date('Y-m-d'))
                                  ->orWhere(function($q) {
                                    $q->where('request_date', '=', date('Y-m-d'))
                                      ->where('request_start_time', '>', date('H:i:s'));
                                  });
                        })
                        ->where('requestor_id', $user->user_id)
                        ->get();
        }
        else if ($type == 2) {
            $bookings = Booking::select('*')
                        ->with('users')
                        ->where('is_confirmed', 1)
                        ->where('is_paid', 1)
                        ->where('request_date', date('Y-m-d'))
                        ->where('request_start_time', '<=', date('H:i:s'))
                        ->where('request_end_time', '>=', date('H:i:s'))
                        ->where('requestor_id', $user->user_id)
                        ->get();
        }
        else if ($type == 3) {
            $bookings = Booking::select('*')
                        ->with('users')
                        ->where('is_confirmed', 1)
                        ->where('is_paid', 1)
                        ->where(function($query) {
                            $query->where('request_date', '<', date('Y-m-d'))
                                  ->orWhere(function($q) {
                                    $q->where('request_date', '=', date('Y-m-d'))
                                      ->where('request_end_time', '<', date('H:i:s'));
                                  });
                        })
                        ->get();
        }
        else if ($type == 4) {
            $bookings = Booking::select('*')
                        ->with('users')
                        ->where('is_confirmed', 0)
                        ->where(function($query) {
                            $query->where('request_date', '>', date('Y-m-d'))
                                  ->orWhere(function($q) {
                                    $q->where('request_date', '=', date('Y-m-d'))
                                      ->where('request_start_time', '>', date('H:i:s'));
                                  });
                        })
                        ->where('requestor_id', $user->user_id)
                        ->get();
        }
        else if ($type == 5) {
            $bookings = Booking::select('*')
                        ->with('users')
                        ->where('is_confirmed', 1)
                        ->where('is_paid', 1)
                        ->where(function($query) {
                            $query->where('request_date', '>', date('Y-m-d'))
                                  ->orWhere(function($q) {
                                    $q->where('request_date', '=', date('Y-m-d'))
                                      ->where('request_start_time', '>', date('H:i:s'));
                                  });
                        })
                        ->where('requestor_id', $user->user_id)
                        ->get();
        }
        
        return response()->json($bookings)
                        ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN,
                            'x-csrf-token' => $request->get('x-csrf-token')
                        ]);
    }
    */

}