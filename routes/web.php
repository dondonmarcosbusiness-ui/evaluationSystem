<?php

use Illuminate\Support\Facades\Route;

// Serve the Vue SPA for all routes except /api
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!(evaluation_system/public/)?api/).*$');

