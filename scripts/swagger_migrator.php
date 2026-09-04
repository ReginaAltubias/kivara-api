<?php

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Str;

$openapiPath = __DIR__ . '/../openapi.yaml';
$swaggerDir = __DIR__ . '/../app/Swagger';
$schemasDir = $swaggerDir . '/Schemas';
$requestsDir = $swaggerDir . '/Requests';
$responsesDir = $swaggerDir . '/Responses';

// Create directories if they don't exist
@mkdir($schemasDir, 0755, true);
@mkdir($requestsDir, 0755, true);
@mkdir($responsesDir, 0755, true);

echo "Lendo $openapiPath ...\n";
$data = Yaml::parseFile($openapiPath);

$schemas = $data['components']['schemas'] ?? [];
$responses = $data['components']['responses'] ?? [];
$requestBodies = $data['components']['requestBodies'] ?? [];

echo "Migrando Schemas (" . count($schemas) . ")...\n";
foreach ($schemas as $schemaName => $schema) {
    if (in_array($schemaName, ['User', 'Profile', 'Tenant', 'Wallet', 'Lesson'])) {
        // Skip already created
        continue;
    }
    
    $properties = [];
    foreach ($schema['properties'] ?? [] as $propName => $propDef) {
        $type = $propDef['type'] ?? 'string';
        $format = isset($propDef['format']) ? ", format: \"{$propDef['format']}\"" : "";
        $example = isset($propDef['example']) ? ", example: " . var_export($propDef['example'], true) : "";
        $ref = isset($propDef['$ref']) ? ", ref: \"{$propDef['$ref']}\"" : "";
        
        $items = "";
        if ($type === 'array') {
            $itemType = $propDef['items']['type'] ?? 'string';
            $items = ", items: new OA\Items(type: \"$itemType\")";
        }
        
        $properties[] = "        new OA\Property(property: \"$propName\", type: \"$type\"$format$example$ref$items)";
    }
    $propertiesStr = implode(",\n", $properties);
    
    $title = $schema['title'] ?? $schemaName;
    $desc = str_replace('"', '\"', $schema['description'] ?? '');

    $content = <<<PHP
<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "$title",
    description: "$desc",
    properties: [
$propertiesStr
    ]
)]
class $schemaName {}

PHP;
    file_put_contents("$schemasDir/$schemaName.php", $content);
}

echo "Migrando Respostas Reutilizáveis...\n";
foreach ($responses as $responseName => $responseDef) {
    if (in_array($responseName, ['ValidationError'])) {
        continue; // Skip
    }
    
    $desc = str_replace('"', '\"', $responseDef['description'] ?? '');
    
    $content = <<<PHP
<?php
namespace App\Swagger\Responses;

use OpenApi\Attributes as OA;

#[OA\Response(
    response: "$responseName",
    description: "$desc",
    content: new OA\JsonContent()
)]
class $responseName {}

PHP;
    file_put_contents("$responsesDir/$responseName.php", $content);
}

echo "Schemas Virtuais e Respostas geradas com sucesso.\n";
echo "Para aplicar nas 225 rotas dos controladores, pode usar o \$ref diretamente.\n";

// TODO: A injecção de anotações directamente nos Controladores requer um mapeamento exacto do operationId para a acção do controlador,
// que por defeito não está estruturado no seu openapi.yaml a não ser que tenha o "x-controller" ou "operationId" no formato Class@method.

