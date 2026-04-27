<?php

namespace App\Models;

const API_VERSION = '2026-04';

class ShopifyStore
{
    /** @var array $stores */
    private array $stores;

    /** @var string $storeName */
    private string $storeName;

    /** @var string $baseUrl */
    private string $baseUrl;

    public function __construct(string $storeName)
    {
        $this->storeName = $storeName;
        $this->stores = getStores();
        $this->baseUrl = "https://$storeName.myshopify.com/admin";
    }

    public function exists(): bool
    {
        return \array_key_exists($this->storeName, $this->stores);
    }

    public function getGraphQlEndpoint(): string|false
    {
        return $this->exists() ? \sprintf("%s/api/%s/graphql", $this->baseUrl, API_VERSION) : false;
    }

    public function getAccessTokenEndpoint(): string|false
    {
        return $this->exists() ? "{$this->baseUrl}/oauth/access_token" : false;
    }

    public function getAccessToken(): string|array|false
    {
        return $this->exists() ? $this->stores[$this->storeName]["accessToken"] : false;
    }
}
