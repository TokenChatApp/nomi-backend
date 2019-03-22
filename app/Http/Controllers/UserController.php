<?php

namespace App\Http\Controllers;

require '../vendor/autoload.php';

use App\BookingItem;
use App\Settings;
use App\User;
use App\Mail\SignupEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use GrahamCampbell\Flysystem\Facades\Flysystem;

class UserController extends Controller
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
        $validator = Validator::make($request->all(), [
           'username' => 'required|max:100',
           'display_name' => 'required|max:100',
           'age' => 'required|numeric',
           'mobile_no' => 'required|max:20',
           'email' => 'required|max:200|email|unique:users,email',
           'password' => 'required|string|max:100',
           'gender' => 'required|max:10',
           'referral' => 'exists:users,username'
        ]);

        if ($request->gender == 'F') {
            $validator = Validator::make($request->all(), [
                'username' => 'required|max:100',
                'display_name' => 'required|max:100',
                'age' => 'required|numeric',
                'mobile_no' => 'required|max:20',
                'email' => 'required|max:200|email|unique:users,email',
                'password' => 'required|string|max:100',
                'gender' => 'required|max:10',
                'city_id' => 'numeric',
                'place_id' => 'numeric',
                'height' => 'numeric',
                'weight' => 'numeric',
                'language' => '',
                'nationality' => '',
                'intro' => 'required',
                'referral' => 'required|exists:users,username'
            ]);
        }
        
        if ($validator->fails()) {
            return response()->make(array('status' => false, 'errorMessage' => 'Unable to signup.',
                                          'errors' => $validator->messages(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }

        if ($request->gender == 'M') {
            // check with lol chat with username
            /*
            $params = 'username='.$request->username.'&password='.$request->password;
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', 'http://admin.tokenchatserver.com/api/user?'.$params, [
                'headers' => [
                    'Accept'     => 'application/json',
                    'X-API-KEY'  => ['go8c8gwkwcc4sggg8wkog00ccwkgc404sk4s8okk']
                ]
            ]);
            $payload = json_decode($response->getBody()->getContents());

            // have body means login success
            if ($payload != null && $payload->pin != '') {
                return response()->make(array('status' => false, 'errorMessage' => 'Unable to signup. Username already exists.',
                                              'errors' => $validator->messages(), 'session' => false), 400)
                                 ->withHeaders([
                                    'Access-Control-Allow-Credentials' => 'true',
                                    'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                    'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                    'Access-Control-Allow-Origin' => Settings::ORIGIN
                                ]);
            }
            */
            $users = User::select('*')
                        ->where('username', $request->username)
                        ->where('gender', 'M')
                        ->get();
            
            if ($users != null && sizeof($users) > 0) {
                return response()->make(array('status' => false, 'errorMessage' => 'Unable to signup.',
                                              'errors' => array('username' => array('Username already exists.')), 'session' => false), 400)
                                 ->withHeaders([
                                    'Access-Control-Allow-Credentials' => 'true',
                                    'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                    'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                    'Access-Control-Allow-Origin' => Settings::ORIGIN
                                ]);
            }
        }

        // create user record
        $user = new User;
        $user->url = str_random(32);
        $user->username = $request->username;
        $user->display_name = $request->display_name;
        $user->password = Hash::make($request->password);
        $user->email = $request->email;
        $user->gender = $request->gender;
        $user->age = $request->age;
        $user->mobile_no = $request->mobile_no;
        $user->rate_level = 0;
        $user->rate_per_session = 0;
        $user->height = 0;
        $user->weight = 0;
        $user->language = '';
        $user->nationality = '';
        $user->referral = $request->referral;
        $user->status = 1;
        $user->last_logged_in = Carbon::now()->toDateTimeString();

        if ($request->gender == 'F') {
            $user->rate_level = 1;
            $user->rate_per_session = 10000; 
            $user->city_id = $request->city_id;
            $user->place_id = $request->place_id;
            $user->height = $request->height;
            $user->weight = $request->weight;
            $user->language = $request->language;
            $user->nationality = $request->nationality;
        }

        if ($request->file('avatar')) {
            $filename = $request->file('avatar')->getClientOriginalName();;
            $image = $request->file('avatar');
            Flysystem::put($filename, File::get($request->file('avatar')));

            $user->avatar = $filename;
        }

        $user->save();

        // create token
        $payload = [
            'iss' => 'nomi-jwt', // Issuer of the token
            'sub' => $user->user_id, // Subject of the token
            'iat' => time(), // Time when JWT was issued. 
            'exp' => time() + 60*60 // Expiration time
        ];
        $token = JWT::encode($payload, env('JWT_SECRET'));

        $user = User::find($user->user_id);
        $user->token = $token;

        $user->save();

        if ($request->gender == 'M') {
            Mail::to($user->email)->send(new SignupEmail($user));
        }
        
        // As you can see we are passing `JWT_SECRET` as the second parameter that will 
        // be used to decode the token in the future.
        return response()->json(array('status' => true, 'errorMessage' => '', 'errors' => array(),
                                      'session' => $token))
                         ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN,
                            'nomi-token' => $token
                        ]);
    }
   
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'place_id' => 'required|numeric',
           'city_id' => 'required|numeric',
           'request_date' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->make(array('status' => false, 'errorMessage' => 'Unable to find available girls.',
                                          'errors' => $validator->messages(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }

        $users = array();
        $available_users = array();

        if ($request->place_id) {
            $users = User::select('*')
                        ->where('place_id', $request->place_id)
                        ->where('gender', 'F')
                        ->get();
        }
        else if ($request->city_id) {
            $users = User::select('*')
                        ->where('city_id', $request->city_id)
                        ->where('gender', 'F')
                        ->get();
        }

        $time = strtotime($request->request_date);
        $start_time = date('Y-m-d H:i', strtotime('-30 minutes', $time));
        $end_time = date('Y-m-d H:i', strtotime('+150 minutes', $time));

        if ($users != null) {
            foreach ($users as $u) {
                $available = true;

                $items = BookingItem::with('booking')
                                    ->with('request_user')
                                    ->where('is_accepted', 1)
                                    ->where('is_selected', 1)
                                    ->where('user_id', $u->user_id)
                                    ->get();
                
                if ($items != null) {
                    foreach ($items as $i) {
                        if ($i->booking->is_confirmed == 1 && 
                            $i->booking->is_paid == 1 &&
                            strtotime($i->booking->request_end_date.' '.$i->booking->request_end_time) > strtotime($start_time) &&
                            strtotime($i->booking->request_date.' '.$i->booking->request_start_time) < strtotime($end_time)) {
                            $available = false;
                        }
                    }

                    if ($available) {
                        $available_users[] = $u;
                    }
                }
                else {
                    $available_users[] = $u;
                }
            }
        }

        return response()->json($available_users)
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
        return response()->json(array('avatar' => $user->avatar,
                                      'display_name' => $user->display_name,
                                      'username' => $user->username,
                                      'email' => $user->email,
                                      'gender' => $user->gender,
                                      'age' => $user->age,
                                      'mobile_no' => $user->mobile_no,
                                      'rate_level' => $user->rate_level,
                                      'rate_per_session' => $user->rate_per_session,
                                      'city_id' => $user->city_id,
                                      'place_id' => $user->place_id,
                                      'height' => $user->height,
                                      'weight' => $user->weight,
                                      'language' => $user->language,
                                      'nationality' => $user->nationality,
                                      'intro' => $user->intro))
                        ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN,
                            'x-csrf-token' => $request->get('x-csrf-token')
                        ]);
    }

    public function upload_avatar(Request $request)
    {
        $user = $request->auth;

        if ($request->file('avatar')) {
            $filename = $request->file('avatar')->getClientOriginalName();;
            $image = $request->file('avatar');
            Flysystem::put($filename, File::get($request->file('avatar')));

            $user->avatar = $filename;
            $user->save();

            return response()->json(array('URLID' => $user->url,
                                          'Username' => $user->username,
                                          'Avatar' => $user->avatar,
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
    }

    public function remove_avatar(Request $request)
    {
        $user = $request->auth;

        Flysystem::delete($user->avatar);

        $user->avatar = '';
        $user->save();

        return response()->json(array('URLID' => $user->url,
                                      'Username' => $user->username,
                                      'Avatar' => $user->avatar,
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
   
    public function show_avatar(Request $request)
    {
        $user = $request->auth;
        $path = storage_path('files').'/'.$user->avatar;

        if ($user->avatar == null || $user->avatar = '' || !File::exists($path)) {
            return;
        }

        $file = File::get($path);
        $type = File::mimeType($path);

        $response = response()->make($file, 200);
        $response->header('Content-Type', $type);

        return $response;
    }

    public function fetch_avatar(Request $request)
    {
        $path = storage_path('files').'/'.$request->avatar;

        if ($request->avatar == null || $request->avatar = '' || !File::exists($path)) {
            return;
        }

        $file = File::get($path);
        $type = File::mimeType($path);

        $response = response()->make($file, 200);
        $response->header('Content-Type', $type);

        return $response;
    }

    public function update(Request $request)
    { 
        $user = $request->auth;

        $validator = Validator::make($request->all(), [
           'place_id' => 'required|numeric',
           'city_id' => 'required|numeric'
        ]);
        
        if ($validator->fails()) {
            return response()->make(array('status' => false, 'errorMessage' => 'Unable to update location.',
                                          'errors' => $validator->messages(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }

        if ($request->place_id) {
            $user->place_id = $request->place_id;
        }
        if ($request->city_id) {
            $user->city_id = $request->city_id;
        }

        $user->save();
        return response()->json(array('URLID' => $user->url,
                                      'Username' => $user->username,
                                      'Avatar' => $user->avatar,
                                      'Gender' => $user->gender,
                                      'Age' => $user->age))
                         ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN
                        ]);
    }

    public function update_intro(Request $request)
    { 
        $user = $request->auth;

        $validator = Validator::make($request->all(), [
           'intro' => 'required'
        ]);
        
        if ($validator->fails()) {
            return response()->make(array('status' => false, 'errorMessage' => 'Unable to update intro.',
                                          'errors' => $validator->messages(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }

        if ($request->intro) {
            $user->intro = $request->intro;
        }

        $user->save();
        return response()->json(array('URLID' => $user->url,
                                      'Username' => $user->username,
                                      'Avatar' => $user->avatar,
                                      'Gender' => $user->gender,
                                      'Age' => $user->age))
                         ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN
                        ]);
    }

}