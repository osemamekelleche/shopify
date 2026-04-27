<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class ShopifyProxyController extends Controller
{
    public function processRequest(Request $request, string $storeName): JsonResponse
    {
        return getProcessRequestResponse($this, $request, $storeName);
    }

    public function getStoreTitle(string $storeName): string|false
    {
        return getStoreTitleResponse($this, $storeName);
    }

    public function getIndexView(Request $request)
    {
        return getIndexViewResponse();
    }

    public function getStoreView(Request $request, string $storeName)
    {
        return getStoreViewResponse($this, $storeName);
    }

    public function getLoginView(Request $request)
    {
        return getLoginViewResponse($request);
    }
}
