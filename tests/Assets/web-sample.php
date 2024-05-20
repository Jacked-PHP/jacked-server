<?php

use App\Events\BroadcastSampleEvent;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Kanata\LaravelBroadcaster\Services\JwtToken;

Route::get('/', function () {
    return 'ok';
});

Route::get('/get-csrf', function () {
    return request()->session()->token();
});

Route::post('/form', function () {
    return request()->all();
});

Route::get('/get-200', function () {
    return response('ok', 200);
});

Route::get('/get-302', function () {
    return redirect('/');
});

Route::get('/get-400', function () {
    return response('400', 400);
});

Route::get('/get-404', function () {
    return response('404', 404);
});

Route::get('/get-500', function () {
    return response('500', 500);
});

Route::middleware('auth:sanctum')->get('/broadcast-sample', function () {
    Config::set('conveyor.query', 'token=' . JwtToken::create(
        name: 'test-1',
        userId: auth()->user()->id,
        expire: 60,
        useLimit: 1,
    )->token);

    broadcast(new BroadcastSampleEvent());
    return response('dispatched', 200);
});
