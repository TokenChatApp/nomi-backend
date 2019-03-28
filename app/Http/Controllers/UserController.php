<?php

namespace App\Http\Controllers;

require '../vendor/autoload.php';

use App\BookingItem;
use App\Photo;
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
        $attributes = [
            'username' => 'ユーザー名',
            'display_name' => 'ニックネーム',
            'age' => '年齢',
            'mobile_no' => '携帯番号',
            'email' => 'メールアドレス',
            'password' => 'パスワード',
            'gender' => '性別'
            //'referral' => '紹介コード'
        ];

        $validator = Validator::make($request->all(), [
           'username' => 'required|max:100',
           'display_name' => 'required|max:100',
           'age' => 'required|numeric',
           'mobile_no' => 'required|max:20',
           'email' => 'required|max:200|email|unique:users,email',
           'password' => 'required|string|max:100',
           'gender' => 'required|max:10'
           //'referral' => 'exists:users,username'
        ], [], $attributes);

        if ($request->gender == 'F') {
            $attributes = [
                'username' => 'ユーザー名',
                'display_name' => 'ニックネーム',
                'age' => '年齢',
                'mobile_no' => '携帯番号',
                'email' => 'メールアドレス',
                'password' => 'パスワード',
                'gender' => '性別',
                'city_id' => '都市',
                'place_id' => '場所',
                'height' => '身長',
                'weight' => '体重',
                'language' => '言語',
                'nationality' => '国籍',
                'intro' => '自己紹介'
                //'referral' => '紹介コード'
            ];

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
                'intro' => 'required'
                //'referral' => 'exists:users,username'
            ], [], $attributes);
        }
        
        if ($validator->fails()) {
            return response()->make(array('status' => false, 'errorMessage' => __('messages.error_signup'),
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
                return response()->make(array('status' => false, 'errorMessage' => __('messages.error_signup'),
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
            $filename = time().'_'.$request->file('avatar')->getClientOriginalName();;
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

    public function show(Request $request)
    {
        $user = $request->auth;
        $user->photos = Photo::where('photo_user_id', $user->user_id)
                             ->get();
        return response()->json($user)
                        ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN,
                            'x-csrf-token' => $request->get('x-csrf-token')
                        ]);
    }
   
    public function update(Request $request)
    { 
        $user = $request->auth;

        $attributes = [
            'place_id' => '都市',
            'city_id' => '場所',
            'intro' => '自己紹介'
        ];

        $validator = Validator::make($request->all(), [
           'place_id' => 'numeric',
           'city_id' => 'numeric',
           'intro' => ''
        ], [], $attributes);
        
        if ($validator->fails()) {
            return response()->make(array('status' => false, 'errorMessage' => __('messages.error_profile_update_location'),
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
        if ($request->intro) {
            $user->intro = $request->intro;
        }
        $user->save();

        $files = $request->file('photos');
        foreach ($files as $photo) {
            $filename = time().'_'.$photo->getClientOriginalName();
            Flysystem::put($filename, File::get($photo));

            $photo = new Photo;
            $photo->photo_url = $filename;
            $photo->photo_user_id = $user->user_id;
            $photo->save();
        }

        $user->photos = Photo::where('photo_user_id', $user->user_id)
                             ->get();
                             
        return response()->json($user)
                         ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN
                        ]);
    }

    public function search(Request $request)
    {
        $attributes = [
            'request_date' => '日にち',
            'city_id' => '都市',
            'place_id' => '場所'
        ];

        $validator = Validator::make($request->all(), [
            'request_date' => 'required',
            'city_id' => 'required|numeric',
            'place_id' => 'required|numeric'
        ], [], $attributes);

        if ($validator->fails()) {
            return response()->make(array('status' => false, 'errorMessage' => __('messages.error_profile_search_available'),
                                          'errors' => $validator->messages(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }

        $girls = array();
        $girls['exact_girls'] = $this->get_available_girls($request->request_date, $request->city_id, $request->place_id);
        $girls['nearby_girls'] = array();
        $nearby_girls = array_diff($this->get_available_girls($request->request_date, $request->city_id, NULL), $girls['exact_girls']);
        foreach ($nearby_girls as $g) {
            $girls['nearby_girls'][] = $g;
        } 

        return response()->json($girls)
                        ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN,
                            'x-csrf-token' => $request->get('x-csrf-token')
                        ]);
    }

    private function get_available_girls($request_date, $city_id, $place_id)
    {
        $users = array();
        $available_users = array();

        if ($place_id != NULL) {
            $users = User::select('*')
                        ->where('place_id', $place_id)
                        ->where('gender', 'F')
                        ->get();
        }
        else if ($city_id != NULL) {
            $users = User::select('*')
                        ->where('city_id', $city_id)
                        ->where('gender', 'F')
                        ->get();
        }

        $time = strtotime($request_date);
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

        return $available_users;
    }

    public function upload_avatar(Request $request)
    {
        $user = $request->auth;

        if ($request->file('avatar')) {
            if ($user->avatar != NULL || $user->avatar != '') {
                if (Flysystem::has($user->avatar)) {
                    Flysystem::delete($user->avatar);
                }
            }

            $filename = time().'_'.$request->file('avatar')->getClientOriginalName();;
            $image = $request->file('avatar');
            Flysystem::put($filename, File::get($request->file('avatar')));

            $user->avatar = $filename;
            $user->save();

            return response()->json($user)
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

        if ($user->avatar != NULL) {
            if (Flysystem::has($user->avatar)) {
                Flysystem::delete($user->avatar);
            }
            $user->avatar = '';
            $user->save();

            return response()->json($user)
                        ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN,
                            'x-csrf-token' => $request->get('x-csrf-token')
                        ]);
        }        

        return response()->make(array('status' => false, 'errorMessage' => __('messages.error_profile_remove_avatar_none'),
                                      'errors' => array(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
    }

    public function upload_photo(Request $request)
    {
        $user = $request->auth;
        
        if ($request->hasFile('photos')) {
            $files = $request->file('photos');
            foreach ($files as $photo) {
                $filename = time().'_'.$photo->getClientOriginalName();
                Flysystem::put($filename, File::get($photo));

                $photo = new Photo;
                $photo->photo_url = $filename;
                $photo->photo_user_id = $user->user_id;
                $photo->save();
            }

            return response()->json($user)
                            ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN,
                                'x-csrf-token' => $request->get('x-csrf-token')
                            ]);
        }
        else {
            return response()->make(array('status' => false, 'errorMessage' => __('messages.error_profile_upload_photo'),
                                          'errors' => array(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }
    }

    public function remove_photo(Request $request)
    {
        $user = $request->auth;

        if (!$request->photo_ids) {
            return;
        }

        $photo_ids = explode(",", $request->photo_ids);
        if ($photo_ids != null) {
            foreach ($photo_ids as $id) {
                $photo = Photo::find($id);

                if ($photo == null) {
                    return response()->make(array('status' => false, 'errorMessage' => __('messages.error_profile_remove_photo_missing'),
                                                  'errors' => array(), 'session' => false), 400)
                                     ->withHeaders([
                                        'Access-Control-Allow-Credentials' => 'true',
                                        'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                        'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                        'Access-Control-Allow-Origin' => Settings::ORIGIN
                                    ]);
                }

                if ($photo->photo_user_id == $user->user_id) {
                    if (Flysystem::has($photo->photo_url)) {
                        Flysystem::delete($photo->photo_url);
                    }
                    Photo::find($id)->delete();
                }
                else {
                    return response()->make(array('status' => false, 'errorMessage' => __('messages.error_profile_remove_photo_denied'),
                                                  'errors' => array(), 'session' => false), 400)
                                     ->withHeaders([
                                        'Access-Control-Allow-Credentials' => 'true',
                                        'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                        'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                        'Access-Control-Allow-Origin' => Settings::ORIGIN
                                    ]);
                }
            }

            return response()->make(array('status' => true, 'message' => __('messages.success_profile_remove_photo'),
                                          'errors' => array(), 'session' => true), 200)
                            ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN,
                                'x-csrf-token' => $request->get('x-csrf-token')
                            ]);
        }        
    }

}