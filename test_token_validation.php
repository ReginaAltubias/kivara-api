<?php
$tokenString = '15|HJDTKbFOU1aKX9cLUIMRCTrOaz5kO8dG82zxnl1Xbbb222cb';
$token = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenString);
if (!$token) {
    echo "TOKEN NOT FOUND\n";
} else {
    echo "TOKEN FOUND. Tokenable type: " . $token->tokenable_type . "\n";
    $user = $token->tokenable;
    if ($user) {
        echo "USER FOUND: " . $user->email . "\n";
    } else {
        echo "USER NOT FOUND FOR TOKENABLE ID: " . $token->tokenable_id . "\n";
    }
}
