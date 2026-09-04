<?php
$request = Request::create('/api/v1/auth/refresh', 'POST', ['refresh_token' => '19|pMfNFNyTAUI65f7iMM6GeLloVocQU3CKA8CcBUqjdc2443bd']);
$request->headers->set('Accept', 'application/json');
$response = app()->handle($request);
echo $response->getContent();
echo "\nSTATUS: " . $response->getStatusCode() . "\n";
