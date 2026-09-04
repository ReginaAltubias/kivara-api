<?php
$request = Request::create('/api/v1/auth/login', 'POST', ['email' => 'reginaaltubias@gmail.com', 'password' => 'Admin123!']);
$request->headers->set('Accept', 'application/json');
$response = app()->handle($request);
echo $response->getContent();
echo "\nSTATUS: " . $response->getStatusCode() . "\n";
