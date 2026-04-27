<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyProxyController;
use App\Http\Middleware\CheckProxyAuth;

Route::controller(ShopifyProxyController::class)->group(function () {
    Route::middleware([CheckProxyAuth::class])->group(function () {
        Route::get('/', 'getIndexView');
        Route::get(
            '/store/{storeName}',
            'getStoreView'
        );
    });

    Route::get('/loginpage', 'getLoginView');
});
