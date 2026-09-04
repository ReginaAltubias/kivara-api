<?php
$request = Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', [
    'email' => 'reginaaltubias@gmail.com',
    'password' => 'Admin123!'
]);
$response = app()->handle($request);
echo 'STATUS: ' . $response->status() . "\n";
echo 'CONTENT: ' . $response->getContent() . "\n";
