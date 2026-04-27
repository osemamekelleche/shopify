<?php

return [
    'stores' => json_decode(json:
        file_exists($path = storage_path('app/private/shopify_stores.json')) ?
            file_get_contents($path) :
            '',
        associative: true
    ) ?? []
];
