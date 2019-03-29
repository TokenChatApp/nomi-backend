<?php

namespace App\Http\Controllers;

require '../vendor/autoload.php';

use App\Settings;
use App\User;
use App\Verification;
use App\Mail\ResetPasswordEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class VerificationController extends Controller
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

    public function reset(Request $request)
    {
        $attributes = [
            'email' => 'メールアドレス'
        ];

        $validator = Validator::make($request->all(), [
           'email' => 'required|max:200|email|exists:users,email'
        ], [], $attributes);
        
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

        $user = User::select('*')
                    ->where('email', $request->email)
                    ->first();
        
        if ($user != null) {
            // find old verification token and delete
            $verification = Verification::where('verification_user_id', $user->user_id)->delete();

            $verification = new Verification;
            $verification->verification_key = str_random(10);
            $verification->verification_user_id = $user->user_id;
            $verification->save();

            Mail::to($request->email)->send(new ResetPasswordEmail($user, $verification));

            return response()->json(array('status' => true, 'message' => __('messages.success_reset_password'), 'errors' => array(),
                                          'session' => false))
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }
    }
   
    public function update(Request $request)
    {
        $attributes = [
            'password' => 'パスワード',
            'password_confirmation' => 'パスワード再度認証',
            'token' => 'トークン'
        ];

        $validator = Validator::make($request->all(), [
           'password' => 'required|confirmed|string|max:100',
           'password_confirmation' => 'required|string|max:100',
           'token' => 'required|string|max:10'
        ], [], $attributes);
        
        if ($validator->fails()) {
            return response()->make(array('status' => false, 'errorMessage' => __('messages.error_reset_password'),
                                          'errors' => $validator->messages(), 'session' => false), 400)
                             ->withHeaders([
                                'Access-Control-Allow-Credentials' => 'true',
                                'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                'Access-Control-Allow-Origin' => Settings::ORIGIN
                            ]);
        }

        $verification = Verification::select('*')
                                    ->where('verification_key', $request->token)
                                    ->first();
        if ($verification != null) {
            $user = User::select('*')
                    ->where('user_id', $verification->verification_user_id)
                    ->first();

            if ($user != null) {
                $user->password = Hash::make($request->password);
                $user->user_date_updated = Carbon::now()->toDateTimeString();
                $user->save();

                $verification->delete();

                return response()->make(array('status' => true, 'message' => __('messages.success_update_password'),
                                              'errors' => array(), 'session' => false), 200)
                                 ->withHeaders([
                                    'Access-Control-Allow-Credentials' => 'true',
                                    'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                                    'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                                    'Access-Control-Allow-Origin' => Settings::ORIGIN
                                ]);
            }
        }
        else {
            return response()->make(array('status' => false, 'errorMessage' => __('messages.error_reset_password_invalid_token'),
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