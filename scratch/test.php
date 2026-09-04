<?php
require __DIR__ . '/../vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
$data = Yaml::parseFile(__DIR__ . '/../openapi.yaml');
echo json_encode(array_slice(array_keys($data['paths'] ?? []), 0, 5));
