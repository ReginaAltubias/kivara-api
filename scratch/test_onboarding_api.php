<?php
$user = App\Models\User::where('email', 'reginaaltubias@gmail.com')->first();
$token = $user->createToken('test-token')->plainTextToken;

$request = Illuminate\Http\Request::create('/api/v1/onboarding/progress', 'GET');
$request->headers->set('Authorization', 'Bearer ' . $token);

$response = app()->handle($request);
echo 'STATUS: ' . $response->status() . "\n";
echo 'CONTENT: ' . $response->getContent() . "\n";
