<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});



$router->group(['prefix' => 'api/v1/'], function() use($router) {
	$router->options('auth/csrf-token', 'AuthController@init');
	$router->get('auth/csrf-token', 'AuthController@index');

	$router->options('auth/sign-up', 'UserController@init');
	$router->post('auth/sign-up', 'UserController@create');

	$router->options('auth/login', 'AuthController@init');
	$router->post('auth/login', 'AuthController@login');

	$router->options('auth/logout', 'AuthController@init');

	$router->options('profile/info', 'UserController@init');
	$router->options('profile/show_avatar', 'UserController@init');
	$router->options('profile/fetch_avatar', 'UserController@init');
	$router->options('profile/search', 'UserController@init');
	$router->options('profile/upload_avatar', 'UserController@init');
	$router->options('profile/remove_avatar', 'UserController@init');

	$router->post('profile/search', 'UserController@search');
	$router->post('profile/fetch_avatar', 'UserController@fetch_avatar');

	$router->options('booking', 'BookingController@init');
	$router->options('booking/show/{id}', 'BookingController@init');
	$router->options('booking/retrieve', 'BookingController@init');
	$router->options('booking/accept', 'BookingController@init');
	$router->options('booking/confirm', 'BookingController@init');

	$router->options('cities', 'CityController@init');
	$router->get('cities', 'CityController@index');

	$router->options('places', 'PlaceController@init');
	$router->get('places', 'PlaceController@index');
	$router->get('places/{id}', 'PlaceController@show');
});

$router->group(['prefix' => 'api/v1/', 'middleware' => 'jwt.auth'], function() use($router) {	
	$router->post('auth/logout', 'AuthController@logout');

	$router->get('profile/info', 'UserController@show');
	$router->get('profile/show_avatar', 'UserController@show_avatar');
	$router->post('profile/upload_avatar', 'UserController@upload_avatar');
	$router->post('profile/remove_avatar', 'UserController@remove_avatar');

	$router->put('booking', 'BookingController@create');
	$router->get('booking/show/{id}', 'BookingController@show');
	$router->get('booking/retrieve', 'BookingController@search');
	$router->post('booking/accept', 'BookingController@accept');
	$router->post('booking/confirm', 'BookingController@confirm');
});