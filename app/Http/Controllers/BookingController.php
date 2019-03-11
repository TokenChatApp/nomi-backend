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

    public function index()
    {     
        $users = User::all();
        return response()->json($users);
    }

    public function create(Request $request)
    {
        $user = $request->auth;

        $validator = Validator::make($request->all(), [
           'requestDate' => 'required',
           'requestStartTime' => 'required',
           'requestEndTime' => 'required',
           'profileIds' => 'required'
        ]);

        if ($validator->fails()) {
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
        $booking->request_date = $request->requestDate;
        $booking->request_start_time = $request->requestStartTime;
        $booking->request_end_time = $request->requestEndTime;
        $booking->request_total_fee = 0;
        $booking->save();

        $booking_id = $booking->request_id;

        // create booking details
        $profileIds = explode(',', $request->profileIds);
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
        
        return response()->json(array('status' => true, 'errorMessage' => '', 'errors' => array(),
                                      'message' => 'Booking created successfully.'))
                         ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN,
                            'x-csrf-token' => $request->get('x-csrf-token')
                        ]);
    }
   
    public function search(Request $request, $type)
    {
        $user = $request->auth;
        $bookings = array();

        if ($type == 1) {
            $bookings = Booking::select('*')
                        ->with('items')
                        ->join('users', 'users.user_id = user_id')
                        ->where('requestor_id', $user->user_id)
                        ->get();
        }
        else if ($type == 2) {
            $bookings = Booking::select('url','avatar','displayname','username','age','rate_per_hour','height','weight','language','nationality')
                        ->get();
        }
        else if ($type == 3) {
            $bookings = Booking::select('url','avatar','displayname','username','age','rate_per_hour','height','weight','language','nationality')
                        ::with()
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

    public function show(Request $request)
    {
        $user = $request->auth;
        return response()->json(array('URLID' => $user->url,
                                      'Username' => $user->username,
                                      'Avatar' => '',
                                      'Gender' => $user->gender,
                                      'Age' => $user->age))
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
        $booking = Booking::find($request->requestId);

        if ($booking != null) {
            $user->save();
            return response()->json($user)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }
        else {
            return response()->make(array('status' => false, 'errorMessage' => 'Unable to create booking.',
                                          'errors' => $validator->messages(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }
        
    }

    public function confirm(Request $request, $id)
    { 
        $user = User::find($id);

        if ($request->placeId) {
            $user->place_id = $request->placeId;
        }
        if ($request->cityId) {
            $user->city_id = $request->cityId;
        }

        $user->save();
        return response()->json($user)
                         ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN
                        ]);
    }

}