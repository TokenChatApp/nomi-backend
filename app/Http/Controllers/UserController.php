<?php

namespace App\Http\Controllers;

require '../vendor/autoload.php';

use App\Settings;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
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
           'username' => 'required|max:100|unique:users,username',
           'displayName' => 'required|max:100',
           'age' => 'required|numeric',
           'mobileNumber' => 'required|max:20',
           'email' => 'required|max:200|email|unique:users,email',
           'password' => 'required|string|max:100',
           'gender' => 'required|max:10'
        ]);

        if ($request->gender == 'Women') {
            $validator = Validator::make($request->all(), [
                'username' => 'required|max:100|unique:users,username',
                'displayName' => 'required|max:100',
                'age' => 'required|numeric',
                'mobileNumber' => 'required|max:20',
                'email' => 'required|max:200|email|unique:users,email',
                'password' => 'required|string|max:100',
                'gender' => 'required|max:10',
                'cityId' => 'numeric',
                'placeId' => 'numeric',
                'height' => 'numeric',
                'weight' => 'numeric',
                'spokenLanguage' => '',
                'nationality' => ''
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

        // create user record
        $user = new User;
        $user->url = str_random(32);
        $user->username = $request->username;
        $user->displayname = $request->displayName;
        $user->password = Hash::make($request->password);
        $user->email = $request->email;
        $user->gender = $request->gender;
        $user->age = $request->age;
        $user->mobile_no = $request->mobileNumber;
        $user->rate_per_hour = 0;
        $user->height = 0;
        $user->weight = 0;
        $user->language = '';
        $user->nationality = '';
        $user->status = 1;
        $user->last_logged_in = Carbon::now('Asia/Singapore')->toDateTimeString();

        if ($request->gender == 'Women') {
            $user->rate_per_hour = 5000; 
            $user->city_id = $request->cityId;
            $user->place_id = $request->placeId;
            $user->height = $request->height;
            $user->weight = $request->weight;
            $user->language = $request->spokenLanguage;
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
        $users = array();

        if ($request->placeId) {
            $users = User::select('url','avatar','displayname','username','age','rate_per_hour','height','weight','language','nationality')
                        ->where('place_id', $request->placeId)
                        ->where('gender', 'Women')
                        ->get();
        }
        if ($request->cityId) {
            $users = User::select('url','avatar','displayname','username','age','rate_per_hour','height','weight','language','nationality')
                        ->where('city_id', $request->cityId)
                        ->where('gender', 'Women')
                        ->get();
        }
        
        return response()->json($users)
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
                                      'displayname' => $user->displayname,
                                      'username' => $user->username,
                                      'email' => $user->email,
                                      'gender' => $user->gender,
                                      'age' => $user->age,
                                      'mobile_no' => $user->mobile_no,
                                      'rate_per_hour' => $user->rate_per_hour,
                                      'city_id' => $user->city_id,
                                      'place_id' => $user->place_id,
                                      'height' => $user->height,
                                      'weight' => $user->weight,
                                      'language' => $user->language,
                                      'nationality' => $user->nationality))
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

        if (!File::exists($path)) {
            abort(404);
        }

        $file = File::get($path);
        $type = File::mimeType($path);

        $response = response()->make($file, 200);
        $response->header("Content-Type", $type);

        return $response;
    }

    public function update(Request $request, $id)
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