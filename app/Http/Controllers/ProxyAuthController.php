<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProxyAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        return getLoginApiResponse($request);
    }
}
