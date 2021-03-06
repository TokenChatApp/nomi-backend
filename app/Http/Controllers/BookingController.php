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
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Charge;

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

        $attributes = [
            'request_date' => '日にち',
            'city_id' => '都市',
            'place_id' => '場所',
            'profile_ids' => 'プロファイルID'
        ];

        $validator = Validator::make($request->all(), [
           'request_date' => 'required',
           'city_id' => 'numeric',
           'place_id' => 'numeric',
           'profile_ids' => 'required'
        ], [], $attributes);

        $strings = explode(" ", $request->request_date);

        if ($validator->fails() || sizeof($strings) != 2) {
            return response()->make(array('status' => false, 'errorMessage' => __('messages.error_booking_create'),
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
        $booking->request_end_date = date('Y-m-d', strtotime($request->request_date)+7200);
        $booking->request_end_time = date('H:i', strtotime($request->request_date)+7200);
        $booking->city_id = $request->city_id;
        $booking->place_id = $request->place_id;
        $booking->request_total_fee = 0;
        $booking->is_confirmed = 0;
        $booking->is_paid = 0;
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
        
        return response()->make(array('status' => true, 'message' => __('messages.success_booking_create'), 'session' => true), 200)
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
                        ->with('place')
                        ->with('city')
                        ->with('requestor')
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
            return response()->make(array('status' => false, 'errorMessage' => __('messages.error_booking_view'),
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

        // upload last action
        $user->last_action = Carbon::now()->toDateTimeString();
        $user->save();

        $today = date('Y-m-d H:i:s');
        $today_date = date('Y-m-d', strtotime($today));
        $today_time = strtotime(date('Y-m-d'));
        $expiry_time = strtotime($today) - (15*60);
        $current_time = strtotime(date('H:i:s'));

        if ($user->gender == 'M') {
            $bookings = Booking::with('users')
                            ->with('place')
                            ->with('city')
                            ->where('requestor_id', $user->user_id)
                            ->get();

            if ($bookings != null) {
                foreach ($bookings as $b) {
                    if ($b->is_confirmed == 1 && $b->is_paid == 1) {
                        if ($b->users[0]->is_selected == 1) {
                            if (strtotime($b->request_date.' '.$b->request_start_time) <= $current_time && 
                                strtotime($b->request_end_date.' '.$b->request_end_time) >= $current_time) {
                                $b->status = 'Ongoing';
                            }
                            else if (strtotime($b->request_end_date.' '.$b->request_end_time) < $current_time) {
                                $b->status = 'Completed';
                            }
                            else if (strtotime($b->request_date.' '.$b->request_start_time) > $current_time) {
                                $b->status = 'Confirmed';
                            }
                        }
                    }
                    else {
                        if (strtotime($b->request_date.' '.$b->request_start_time) < $expiry_time) {
                            $b->status = 'Expired';
                        }
                        else {
                            $b->status = 'Pending';                            
                        }
                    }
                }
            }
        }
        else if ($user->gender == 'F') {
            $my_bookings = array();
            $bookings = Booking::with(['users' => function($query) use ($user) {
                                $query->where('requests_items.user_id', $user->user_id);
                            }])
                            ->with('place')
                            ->with('city')
                            ->with('requestor')
                            ->get();

            if ($bookings != null) {
                foreach ($bookings as $b) {
                    // filter out all bookings that are not mine
                    if (sizeof($b->users) == 0)
                        continue;

                    $found = FALSE;
                    foreach ($b->users as $u) {
                        if ($u->user_id == $user->user_id) {
                            $found = TRUE;
                        }
                    }

                    if (!$found)
                        continue;

                    if ($b->is_confirmed == 1 && $b->is_paid == 1) {
                        if ($b->users[0]->is_selected == 1) {
                            if (strtotime($b->request_date.' '.$b->request_start_time) <= $current_time && 
                                strtotime($b->request_end_date.' '.$b->request_end_time) >= $current_time) {
                                $b->status = 'Ongoing';
                            }
                            else if (strtotime($b->request_end_date.' '.$b->request_end_time) < $current_time) {
                                $b->status = 'Completed';
                            }
                            else if (strtotime($b->request_date.' '.$b->request_start_time) > $current_time) {
                                $b->status = 'Confirmed';
                            }
                        }
                        else if ($b->users[0]->is_selected == 0) {
                            $b->status = 'Cancelled';
                        }
                    }
                    else {
                        if (strtotime($b->request_date.' '.$b->request_start_time) < $expiry_time) {
                            $b->status = 'Expired';
                        }
                        else {
                            if ($b->users[0]->is_accepted == 0) {
                                $b->status = 'Pending';
                            }
                            else if ($b->users[0]->is_accepted == 1) {
                                $b->status = 'Accepted';
                            }
                            else if ($b->users[0]->is_accepted == 2) {
                                $b->status = 'Rejected';
                            }
                        }
                    }

                    $my_bookings[] = $b;
                }
            }

            $bookings = $my_bookings;
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

        $attributes = [
            'request_id' => '予約ID',
            'accepted' => '受け入れた'
        ];

        $validator = Validator::make($request->all(), [
           'request_id' => 'required',
           'accepted' => 'required'
        ], [], $attributes);

        if ($validator->fails()) {
            return response()->make(array('status' => false, 'errorMessage' => __('messages.error_booking_accept'),
                                          'errors' => $validator->messages(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }

        $booking = BookingItem::with('booking')
                              ->where('request_id', $request->request_id)
                              ->where('user_id', $user->user_id)
                              ->first();

        if ($booking != null) {
            $booking->is_accepted = $request->accepted;
            $booking->save();
            
            return response()->make(array('status' => true, 'message' => __('messages.success_booking_updated'), 'session' => true), 200)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }
        else {
            return response()->make(array('status' => false, 'errorMessage' => __('messages.error_booking_fetch'),
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

        $attributes = [
            'request_id' => '予約ID',
            'profile_ids' => 'プロファイルID',
            'stripe_token' => '支払いトークン'
        ];

        $validator = Validator::make($request->all(), [
           'request_id' => 'required',
           'profile_ids' => 'required',
           'stripe_token' => 'required'
        ], [], $attributes);

        if ($validator->fails()) {
            return response()->make(array('status' => false, 'errorMessage' => __('messages.error_booking_confirm'),
                                          'errors' => $validator->messages(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }

        $booking = Booking::find($request->request_id);
        if ($booking != null) {
            $total_amount = 0;

            $profileIds = explode(',', $request->profile_ids);
            if ($profileIds != NULL) {
                foreach ($profileIds as $id) {                    
                    $item = BookingItem::with('booking')
                                        ->with('request_user')
                                        ->where('request_id', $request->request_id)
                                        ->where('user_id', $id)
                                        ->first();
                    $total_amount += $item->request_user->rate_per_session;
                }
            }

            try {
                Stripe::setApiKey(env('STRIPE_SECRET'));
     
                $customer = Customer::create(array(
                    'email'  => 'stripe@lolchat.net',
                    'source' => $request->stripe_token
                ));

                $charge = Charge::create(array(
                    'customer' => $customer->id,
                    'amount'   => $total_amount,
                    'currency' => 'jpy',
                    'description' => 'Booking '.$request->request_id
                ));

                $profiles = array();
                if ($profileIds != NULL) {
                    foreach ($profileIds as $id) {                    
                        $item = BookingItem::with('booking')
                                            ->with('request_user')
                                            ->where('request_id', $request->request_id)
                                            ->where('user_id', $id)
                                            ->first();
                        $item->is_selected = 1;
                        $item->save();

                        $profiles[] = $item;
                    }
                }

                $booking->is_confirmed = 1;
                $booking->is_paid = 1;
                $booking->request_total_fee = $total_amount;
                $booking->save();
                
                return response()->make(array('status' => true, 'message' => __('messages.success_booking_confirmed'), 'session' => true, 'profiles' => $profiles), 200)
                                 ->withHeaders([
                                    'Access-Control-Allow-Credentials' => 'true',
                                    'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                    'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                    'Access-Control-Allow-Origin' => Settings::ORIGIN
                                ]);
            }
            catch (\Exception $ex) {
                return $ex->getMessage();
            }
        }
        else {
            return response()->make(array('status' => false, 'errorMessage' => __('messages.error_booking_fetch'),
                                          'errors' => array(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }
    }

}
