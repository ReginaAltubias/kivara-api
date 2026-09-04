<?php
$u = \App\Models\User::where("email", "reginaaltubias@gmail.com")->first();
if (!$u) {
    echo "NO USER";
    exit;
}
try {
    echo "TOKEN: " . $u->createToken("test")->plainTextToken;
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
