<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/docs', function () {
    return view('swagger', [
        'specUrl' => url('/openapi.json'),
    ]);
});

Route::get('/api/admin/docs', function () {
    return view('swagger', [
        'specUrl' => url('/openapi-admin.json'),
    ]);
});
