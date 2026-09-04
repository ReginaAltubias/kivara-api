<?php
$data = [
    'name' => 'Colégio KIVARA do Futuro',
    'nif' => '5412345678',
    'currency' => 'AOA',
    'school_type' => 'private',
    'education_level' => 'Ensino Primário e Iº Ciclo',
    'phone' => '+244 923 456 789',
    'contact_email' => 'dulcealrubias@gmail.com',
    'website' => 'https://www.kivarafuturo.ao',
    'country' => 'AO',
    'state' => 'Luanda',
    'city' => 'Talatona',
    'address' => 'Rua Principal, Urbanização Nova Vida, Talatona, Luanda',
    'director_name' => 'Manuel António Joaquim',
    'director_phone' => '+244 924 567 890',
    'director_email' => 'dulcealtubias@gmail.com',
    'subscription_plan' => 'Premium'
];

// 1. Submit first application
$request1 = Request::create('/api/v1/b2b/school-applications', 'POST', $data);
$request1->headers->set('Accept', 'application/json');
$response1 = app()->handle($request1);

echo "--- FIRST SUBMISSION ---\n";
echo "STATUS: " . $response1->getStatusCode() . "\n";
echo $response1->getContent() . "\n\n";

// 2. Submit second application (with same email but different NIF)
$data['nif'] = '9876543210'; // change NIF because NIF has unique rule
$request2 = Request::create('/api/v1/b2b/school-applications', 'POST', $data);
$request2->headers->set('Accept', 'application/json');
$response2 = app()->handle($request2);

echo "--- SECOND SUBMISSION (Same email, diff NIF) ---\n";
echo "STATUS: " . $response2->getStatusCode() . "\n";
echo $response2->getContent() . "\n";
