<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;

class AuthController extends Controller
{
    public function __construct() { }

    public function init()
    {
        return response()->make("", 204)
                         ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => 'http://192.168.131.12:3000'
                        ]);;
    }

    public function index()
    {     
        return response()->make(str_random(40), 200)
                         ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => 'http://192.168.131.12:3000'
                        ]);;
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'username' => 'required',
           'password' => 'required'
        ]);
        
        if ($validator->fails()) {
            return response()->make(array('status' => false, 'errorMessage' => 'Unable to login.',
                                          'errors' => $validator->messages(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => 'http://192.168.131.12:3000'
                            ]);
        }

        $user = User::where('username', $request->username)->first();
        if ($user == null) {
            return response()->make(array('status' => false, 'errorMessage' => 'Unable to login. Username not found',
                                          'errors' => array(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => 'http://192.168.131.12:3000'
                            ]);
        }
        else if ($user != null && !Hash::check($request->password, $user->password)) {
            return response()->make(array('status' => false, 'errorMessage' => 'Unable to login. Password is incorrect',
                                          'errors' => array(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => 'http://192.168.131.12:3000'
                            ]);
        }

        // create token
        $payload = [
            'iss' => 'nomi-jwt', // Issuer of the token
            'sub' => $user->user_id, // Subject of the token
            'iat' => time(), // Time when JWT was issued. 
            'exp' => time() + 60*60*24*14 // Expiration time
        ];
        $token = JWT::encode($payload, env('JWT_SECRET'));

        $user->token = $token;
        $user->last_logged_in = Carbon::now('Asia/Singapore')->toDateTimeString();
        $user->save();

        // As you can see we are passing `JWT_SECRET` as the second parameter that will 
        // be used to decode the token in the future.
        return response()->json(array('status' => true, 'errorMessage' => '', 'errors' => array(),
                                      'session' => $token))
                         ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => 'http://192.168.131.12:3000',
                            'nomi-token' => $token
                        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->auth;
        $user->token = '';
        $user->save();

        return response()->make("", 200)
                        ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => 'http://192.168.131.12:3000'
                        ]);
    }

}