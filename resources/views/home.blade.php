<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopify Stores</title>
    
</head>

<body>
    <ul>
    
        @foreach ($stores as $store)
        <li>
            <h3>
                <a href="{{ $baseUrl }}/{{ $store }}" target="_blank">{{$store}}</a>
            </h3>
        </li>
        @endforeach
    </ul>
</body>

</html>