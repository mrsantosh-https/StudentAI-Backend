<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/run-migrate-123', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate --force');
        return 'OK: ' . \Illuminate\Support\Facades\Artisan::output();
    } catch (\Throwable $e) {
        return 'FAIL: ' . $e->getMessage();
    }
});
