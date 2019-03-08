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

	$router->options('profile/search', 'UserController@search');
	$router->post('profile/search', 'UserController@search');

	$router->options('cities', 'CityController@init');
	$router->get('cities', 'CityController@index');

	$router->options('places', 'PlaceController@init');
	$router->get('places', 'PlaceController@index');
	$router->get('places/{id}', 'PlaceController@show');
});

$router->group(['prefix' => 'api/v1/', 'middleware' => 'jwt.auth'], function() use($router) {	
	$router->post('profile/info', 'UserController@show');

	$router->post('auth/logout', 'AuthController@logout');
});