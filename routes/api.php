<?php

use App\Http\Controllers\ProxyAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyProxyController;
use App\Http\Middleware\CheckProxyAuth;

Route::controller(ProxyAuthController::class)->group(function () {
    //Public login route to get the cookie    
    Route::match(['POST', 'HEAD'], '/proxy/login', 'login');
});

Route::controller(ShopifyProxyController::class)->group(function () {
    // Protected Shopify routes
    Route::middleware([CheckProxyAuth::class])->group(function () {
        Route::post(
            '/graphql/{storeName}',
            'processRequest'
        );
    });
});
