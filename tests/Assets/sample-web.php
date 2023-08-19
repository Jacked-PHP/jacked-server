<?php

use Illuminate\Support\Facades\Route;

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
