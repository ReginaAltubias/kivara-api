<?php
$request = Illuminate\Http\Request::create('/api/v1/currencies', 'GET');
$response = app()->handle($request);
echo 'STATUS: ' . $response->status() . "\n";
echo 'CONTENT: ' . $response->getContent() . "\n";
