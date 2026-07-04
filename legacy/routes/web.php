<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
Route::get('ride/"{url}"', function($url){
    $url2 = base64_decode($url);
    $filename = 'ride.pdf';
    $tempImage = tempnam(sys_get_temp_dir(), $filename);
    copy($url2, $tempImage);

    return response()->download($tempImage, $filename);
});
Route::get('xml/"{url}"', function($url){
    $url2 = base64_decode($url);
    $filename = 'xml.xml';
    $tempImage = tempnam(sys_get_temp_dir(), $filename);
    copy($url2, $tempImage);

    return response()->download($tempImage, $filename);
});
Route::get('ver_facturas', 'ComprobantesController@ver_facturas')->name('ver_facturas');
