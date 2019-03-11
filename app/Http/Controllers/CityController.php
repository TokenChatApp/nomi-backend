<?php

namespace App\Http\Controllers;

require '../vendor/autoload.php';

use App\City;
use App\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;

class CityController extends Controller
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
                        ]);
    }

    public function index(Request $request)
    {
        $cities = City::all();
        return response()->json($cities)
                        ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => Settings::ORIGIN,
                            'x-csrf-token' => $request->get('x-csrf-token')
                        ]);
    }

}