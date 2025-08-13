<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Documentation route
Route::get('/documentation', function () {
    return view('documentation');
});

// Email verification routes
Route::get('/verify-email', function () {
    return view('verify-email');
});

Route::get('/email-verified', function () {
    return view('email-verified');
});