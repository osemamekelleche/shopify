<?php

return [
    'stores' => json_decode(json:
        file_exists($path = base_path('../private_data/shopify_stores.json')) ?
            file_get_contents($path) :
            '',
        associative: true
    ) ?? []
];
