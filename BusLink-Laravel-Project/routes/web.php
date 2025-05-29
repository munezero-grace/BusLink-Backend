<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to BusLink API',
        'status' => 'running',
        'version' => '1.0.0',
    ]);
});
