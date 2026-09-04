<?php
$request = Request::create('/api/v1/onboarding/progress', 'PUT', ['current_step' => 1]);
$request->headers->set('Accept', 'application/json');
$request->headers->set('Authorization', 'Bearer 15|HJDTKbFOU1aKX9cLUIMRCTrOaz5kO8dG82zxnl1Xbbb222cb');
$response = app()->handle($request);
echo $response->getContent();
echo "\nSTATUS: " . $response->getStatusCode() . "\n";
