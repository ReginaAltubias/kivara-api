<?php
$u = new \App\Models\User; 
$u->name = "Regina Admin"; 
$u->email = "reginaaltubias@gmail.com"; 
$u->password = \Illuminate\Support\Facades\Hash::make("Admin123!"); 
$u->role = "admin"; 
$u->save(); 
echo "DONE";
