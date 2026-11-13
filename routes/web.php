<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/run-migrate-123', function () {
    Artisan::call('migrate --force');
    return 'Migrate Done - Tables ban gayi';
});