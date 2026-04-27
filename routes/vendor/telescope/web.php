<?php

use Illuminate\Support\Facades\Route;

Route::get(
    '/login',
    fn() => response()->json([
        'ok' => true
    ])
);
