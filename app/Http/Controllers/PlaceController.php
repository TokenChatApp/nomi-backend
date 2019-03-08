<?php

namespace App\Http\Controllers;

require '../vendor/autoload.php';

use App\Place;
use App\City;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;

class PlaceController extends Controller
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
                            'Access-Control-Allow-Origin' => $_SERVER['HTTP_ORIGIN']
                        ]);
    }

    public function index(Request $request)
    {     
        $places = Place::all();
        return response()->json($places)
                        ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => $_SERVER['HTTP_ORIGIN'],
                            'x-csrf-token' => $request->get('x-csrf-token')
                        ]);
    }
   
    public function show($id, Request $request)
    {
        $places = Place::where('place_city_id', $id)->get();
        return response()->json($places)
                        ->withHeaders([
                            'Access-Control-Allow-Credentials' => 'true',
                            'Access-Control-Allow-Headers' => 'X-CSRF-Token, X-Requested-With, X-authentication, Content-Type, X-client, Authorization, Accept, Nomi-Token',
                            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                            'Access-Control-Allow-Origin' => $_SERVER['HTTP_ORIGIN'],
                            'x-csrf-token' => $request->get('x-csrf-token')
                        ]);
    }

}