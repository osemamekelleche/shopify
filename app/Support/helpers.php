<?php

use App\Http\Controllers\HttpErrorsController;
use App\Http\Controllers\ShopifyProxyController;
use App\Models\ShopifyStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Uri;
use Symfony\Component\HttpFoundation\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

const SHOPIFY_API_VERSION_HEADER_KEY = 'x-shopify-api-version';
const SHOPIFY_ACCESS_TOKEN_HEADER_KEY = 'X-Shopify-Access-Token';
const PROXY_SECRET_KEY = 'proxy_secret';
const TELESCOP_TOKEN_KEY = 'telescope_token';

const TELESCOPE_AUTH_PASSWORD_KEY = 'telescope_password';
const IS_TELESCOPE_AUTHENTICATED_KEY = 'is_telescope_authenticated';


if (!function_exists('getProxySecret')) {
    function getProxySecret()
    {
        $proxySecret = isTelescopeAuthenticated() ? config('telescope.token') : config('app.proxy_app_key');
        $sessionId = request()->session()->getId();
        return "{$proxySecret}_{$sessionId}";
    }
}

if (!function_exists('getSessionExprireKey')) {
    function getSessionExprireKey(): string
    {
        return isTelescopeAuthenticated() ? TELESCOP_TOKEN_KEY . '_expires_at' : PROXY_SECRET_KEY . '_expires_at';
    }
}

if (!function_exists('getRedirectUrl')) {
    function getRedirectUrl(Request $request)
    {
        $path = '/';
        $defaultRedirectUrl = url($path);
        $redirectUrl = $defaultRedirectUrl;
        if ($request->query->has('redirect')) {
            $redirectUrl = $request->query('redirect');
            if (Uri::of($redirectUrl)->host() !== Uri::of($defaultRedirectUrl)->host()) {
                return $defaultRedirectUrl;
            }
            $path = Uri::of($redirectUrl)->path();
        }
        try {
            Route::getRoutes()->match(request()->create($path));
        } catch (Exception $e) {
            $redirectUrl = $defaultRedirectUrl;
        }

        return $redirectUrl;
    }
}

if (!function_exists('getBody')) {
    function getBody(string $body): array
    {
        $requestBody = [];

        $isJson      = json_validate($body);

        if ($isJson) {
            $requestBody = json_decode($body, associative: true);
        } else {
            if (str_contains($body, '=')) {
                parse_str($body, $requestBody);
            }
        }

        return [$isJson, $requestBody];
    }
}

if (!function_exists('createProxySecretCookie')) {
    function createProxySecretCookie($expire)
    {
        return Cookie::create(
            name: isTelescopeAuthenticated() ? TELESCOP_TOKEN_KEY : PROXY_SECRET_KEY, // name
            value: getProxySecret(), // value
            expire: $expire, // minutes
            path: '/', // path
            secure: true, // Secure only in prod
            httpOnly: true,                     // HttpOnly
            sameSite: null //samesite 
        );
    }
}

if (!function_exists('getProxySecretExpire')) {
    function getProxySecretExpire(Request $request)
    {
        $key = getSessionExprireKey();
        return $request->session()->get($key, now()->addDay());
    }
}

if (!function_exists('setProxySecretExpire')) {
    function setProxySecretExpire(Request $request, $expire)
    {
        $key = getSessionExprireKey();
        $request->session()->put($key, $expire);
    }
}

if (!function_exists('isAuthSuccessful')) {
    function isAuthSuccessful(Request $request)
    {
        $token = $request->input('token', '');
        $password = $request->input('password', '');

        if (empty($password) || empty($token)) {
            return false;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secretkey'),
            'response' => $token
        ]);

        $success = $response->json('success', false);
        $score = $response->json('score', 0);
        $action = $response->json('action', '');

        $unsuccessfulAuth = [
            !$success,
            $score < 0.5,
            $action !== 'submit',
            !in_array($password, [
                hash('sha256', config('app.proxy_password')),
                hash('sha256', config('telescope.password'))
            ], true),
            !$request->hasCookie(config('session.cookie'))
        ];

        foreach ($unsuccessfulAuth as $value) {
            if ($value) {
                return false;
            }
        }

        setTelescopeAuthenticated($password === hash('sha256', config('telescope.password')));
        return true;
    }
}

if (!function_exists('isAuthenticated')) {
    function isAuthenticated(Request $request)
    {

        return in_array(
            getProxySecret(),
            [
                $request->cookie(PROXY_SECRET_KEY),
                $request->cookie(TELESCOPE_TOKEN_KEY)
            ]
        );
    }
}

if (!function_exists('redirectOrAbort')) {
    function redirectOrAbort(Request $request)
    {
        $request->session()->invalidate();
        $url = $request->fullUrl();
        if (str_starts_with($url, url('/api/'))) {
            return response()->json([
                'status' => 403,
                'error' => 'Forbidden access'
            ], 403);
        }

        $redirectUrl = url('/loginpage') . "?redirect=" . rawurlencode($url);
        return redirect()->guest($redirectUrl);
    }
}


if (!function_exists('getAccessToken')) {
    function getAccessToken(ShopifyProxyController $controller, string $storeName, string|array $appCredentials, bool $force = false, int $attempts = 3): string
    {
        $storeKey = $storeName . '_shopify_token';

        $accessToken = $appCredentials;

        if (is_string($accessToken)) {
            return $accessToken;
        }

        if (!is_array($accessToken)) {
            return '';
        }

        if ($attempts == 0) {
            return '';
        }

        if (!$force) {
            $accessToken = request()->session()->get($storeKey, '');
            if ($accessToken == '') {
                $accessToken = getAccessToken(
                    controller: $controller,
                    storeName: $storeName,
                    appCredentials: $appCredentials,
                    force: true,
                    attempts: $attempts - 1
                );
                request()->session()->put($storeKey, $accessToken);
            }
            return $accessToken;
        }

        $body = [
            'grant_type'    => 'client_credentials',
            'client_id'     => $appCredentials['clientId'],
            'client_secret' => $appCredentials['secret'],
        ];

        $body = http_build_query($body);

        $request = new Request(content: $body);
        $request->setMethod('POST');
        $response = $controller->processRequest($request, $storeName);
        if ($response->getStatusCode() >= 400) {
            usleep(250000);
            return getAccessToken($controller, $storeName, $appCredentials, true, $attempts - 1);
        }

        $data = $response->getData();
        $accessToken = $data->access_token;
        request()->session()->put($storeKey, $accessToken);
        return $accessToken;
    }
}


if (!function_exists('getResponseAndStatus')) {
    function getResponseAndStatus(ShopifyProxyController $controller, $storeName, $accessToken, $endpoint, $body, $isJson, $force = false)
    {
        $response = $isJson ? Http::asJson()->withHeaders([
            SHOPIFY_ACCESS_TOKEN_HEADER_KEY => getAccessToken(
                controller: $controller,
                storeName: $storeName,
                appCredentials: $accessToken,
                force: $force
            )
        ])->post($endpoint, $body) : Http::asForm()->post($endpoint, $body);

        return [$response, $response->status()];
    }
}

if (!function_exists('getResponseData')) {
    function getResponseData(Response $response, $status, $isJson)
    {

        if (!$response->object()) {
            return HttpErrorsController::sendHttpBadRequest();
        }


        $responseData = new \stdClass();

        if ($isJson && $response->header(SHOPIFY_API_VERSION_HEADER_KEY) !== '') {
            $responseData->apiVersion = $response->header(SHOPIFY_API_VERSION_HEADER_KEY);
        }

        foreach ($response->object() as $key => $value) {
            $responseData->$key = $value;
        }

        return new JsonResponse($responseData, $status);
    }
}

if (!function_exists('getStoreTitleResponse')) {
    function getStoreTitleResponse(ShopifyProxyController $controller, string $storeName): string|false
    {
        $payload = new \stdClass();
        $payload->query = '{ shop { name myshopifyDomain } }';
        $payload = json_encode($payload);

        $request = new Request(content: $payload);

        $request->setMethod('POST');

        $response = $controller->processRequest($request, $storeName);

        if ($response->getStatusCode() >= 400) {
            return false;
        }

        $data = $response->getData();
        return "{$data->data->shop->name} ({$data->data->shop->myshopifyDomain})";
    }
}

if (!function_exists('getProcessRequestResponse')) {
    function getProcessRequestResponse(ShopifyProxyController $controller, Request $request, string $storeName)
    {
        if ($request->getMethod() !== 'POST') {
            return HttpErrorsController::sendHttpMethodNotAllowed();
        }

        $store = new ShopifyStore($storeName);
        if (!$store->exists()) {
            return HttpErrorsController::sendHttpNotFound();
        }

        [$isJson, $body] = getBody($request->getContent());

        if (empty($body)) {
            return HttpErrorsController::sendHttpUnsupportedMediaType();
        }

        $endpoint = $isJson ? $store->getGraphQlEndpoint() : $store->getAccessTokenEndpoint();

        [$response, $status] = getResponseAndStatus(
            controller: $controller,
            storeName: $storeName,
            accessToken: $store->getAccessToken(),
            endpoint: $endpoint,
            body: $body,
            isJson: $isJson
        );

        if ($status == 401 && $isJson) {
            [$response, $status] = getResponseAndStatus(
                controller: $controller,
                storeName: $storeName,
                accessToken: $store->getAccessToken(),
                endpoint: $endpoint,
                body: $body,
                isJson: $isJson,
                force: true
            );
        }

        return getResponseData($response, $status, $isJson);
    }
}

if (!function_exists('getIndexViewResponse')) {
    function getIndexViewResponse()
    {
        if (empty($stores = getAllStores())) {
            abort(404);
        }
        return view('home', [
            'baseUrl' => url('/store'),
            'stores' => $stores
        ]);
    }
}

if (!function_exists('getStoreViewResponse')) {
    function getStoreViewResponse(ShopifyProxyController $controller, string $storeName)
    {
        if (!($title = $controller->getStoreTitle($storeName))) {
            abort(404);
        }

        return view('shopify', [
            'title' => $title,
            'endpoint' => url("/api/graphql/$storeName")
        ]);
    }
}

if (!function_exists('getLoginViewResponse')) {
    function getLoginViewResponse(Request $request)
    {
        $redirectUrl = getRedirectUrl($request);

        if (isAuthenticated($request)) {
            return redirect()->to($redirectUrl);
        }

        return view('loginpage', [
            'loginUrl' => url('/api/proxy/login'),
            'redirectUrl' => $redirectUrl,
            'recaptchaSiteKey' => config('services.recaptcha.sitekey')
        ]);
    }
}

if (!function_exists('getLoginApiResponse')) {
    function getLoginApiResponse(Request $request)
    {
        if ($request->isMethod('HEAD')) {
            return response()->json(['ok' => true]);
        }

        if (!isAuthSuccessful($request)) {
            return response()->json([
                'status' => 403,
                'error' => 'Forbidden access'
            ], 403);
        }



        $expire = now()->addDay();
        setProxySecretExpire($request, $expire);

        // 2. Return response with the HttpOnly Cookie
        return response()->json(['message' => 'Secure session established'])
            ->cookie(
                createProxySecretCookie($expire)
            );
    }
}

if (!function_exists('handleCheckProxyAuth')) {
    function handleCheckProxyAuth(Request $request, Closure $next)
    {
        if (!isAuthenticated($request)) {
            return redirectOrAbort($request);
        }

        /** @var SymfonyResponse $response */
        $response = $next($request);

        $response->headers->setCookie(
            createProxySecretCookie(getProxySecretExpire($request))
        );
        return $response;
    }
}

if (!function_exists('getAllStores')) {
    function getAllStores()
    {
        return array_keys(getStores());
    }
}

if (!function_exists('getStores')) {
    function getStores(): array
    {
        return config('shopify.stores');
    }
}

if (!function_exists('isTelescopeAuthenticated')) {
    function isTelescopeAuthenticated()
    {
        return request()->session()->get(IS_TELESCOPE_AUTHENTICATED_KEY, false);
    }
}

if (!function_exists('setTelescopeAuthenticated')) {
    function setTelescopeAuthenticated($value)
    {
        request()->session()->put(IS_TELESCOPE_AUTHENTICATED_KEY, $value);
    }
}
