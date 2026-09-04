<?php

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$openapiPath = __DIR__ . '/../openapi.yaml';
$data = Yaml::parseFile($openapiPath);
$paths = $data['paths'] ?? [];

$routesJson = shell_exec('php artisan route:list --path=api/v1 --json');
$routes = json_decode($routesJson, true);

if (!$routes) {
    die("Erro ao obter rotas do Laravel.\n");
}

$controllersToUpdate = [];

foreach ($routes as $route) {
    $uri = '/' . ltrim($route['uri'], '/');
    $uri = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '{$1}', $uri); // Normalize Laravel params to Swagger
    
    // OpenAPI paths in this project omit /api/v1 because it's defined in servers
    $openapiUri = str_replace('/api/v1', '', $uri);
    
    $methods = explode('|', $route['method']);
    foreach ($methods as $method) {
        $methodLower = strtolower($method);
        if ($methodLower === 'head') continue;
        
        if (isset($paths[$openapiUri][$methodLower])) {
            $op = $paths[$openapiUri][$methodLower];
            
            $action = $route['action']; // App\Http\Controllers\Api\UserController@index
            if ($action === 'Closure' || strpos($action, '@') === false) continue;
            
            list($controller, $methodName) = explode('@', $action);
            
            $summary = str_replace('"', '\"', $op['summary'] ?? '');
            $tags = isset($op['tags']) ? '["' . implode('", "', $op['tags']) . '"]' : '[]';
            
            $methodUpper = ucfirst($methodLower);
            
            $annotation = "    #[OA\\$methodUpper(\n";
            $annotation .= "        path: \"$uri\",\n";
            $annotation .= "        summary: \"$summary\",\n";
            $annotation .= "        tags: $tags,\n";
            $annotation .= "        responses: [\n";
            
            $respCount = count($op['responses'] ?? []);
            $i = 0;
            foreach ($op['responses'] ?? [] as $code => $resp) {
                $i++;
                $comma = $i < $respCount ? "," : "";
                
                if ($code == '422') {
                    $annotation .= "            new OA\Response(response: $code, ref: \"#/components/responses/ValidationError\")$comma\n";
                } elseif ($code == '401') {
                     $annotation .= "            new OA\Response(response: $code, description: \"Não autorizado\")$comma\n";
                } else {
                     $desc = str_replace('"', '\"', $resp['description'] ?? '');
                     $annotation .= "            new OA\Response(response: $code, description: \"$desc\")$comma\n";
                }
            }
            $annotation .= "        ]\n";
            $annotation .= "    )]";
            
            $controllersToUpdate[$controller][$methodName] = $annotation;
        }
    }
}

$updatedFiles = 0;

foreach ($controllersToUpdate as $controllerClass => $methods) {
    $reflection = null;
    try {
        $reflection = new ReflectionClass($controllerClass);
    } catch (\Exception $e) {
        continue; // Could not find controller class
    }
    
    $filePath = $reflection->getFileName();
    if (!$filePath || !file_exists($filePath)) continue;
    
    $fileContent = file_get_contents($filePath);
    $originalContent = $fileContent;
    
    // Ensure "use OpenApi\Attributes as OA;" is present
    if (strpos($fileContent, 'OpenApi\Attributes as OA') === false) {
        $fileContent = preg_replace('/(namespace [^;]+;)/', "$1\n\nuse OpenApi\Attributes as OA;", $fileContent);
    }
    
    foreach ($methods as $methodName => $annotation) {
        // Find "public function methodName("
        // If it already has #[OA\, skip
        $pattern = '/(public\s+function\s+' . $methodName . '\s*\()/i';
        
        if (preg_match($pattern, $fileContent, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1];
            
            // Check if there is already an OA attribute right before it
            $beforeStr = substr($fileContent, max(0, $pos - 200), 200);
            if (strpos($beforeStr, '#[OA\\') !== false) {
                // Probably already annotated, skip
                continue;
            }
            
            // Try to find the docblock above it to replace, or just insert above
            // Find the start of the docblock if exists
            $docblockPattern = '/(\/\*\*.*?\*\/)\s*public\s+function\s+' . $methodName . '\s*\(/is';
            if (preg_match($docblockPattern, $fileContent, $docMatches, PREG_OFFSET_CAPTURE)) {
                // Replace the docblock completely with the annotation
                // Wait, removing docblocks might delete other important info. Let's just insert above the function.
            }
            
            // Insert annotation directly above the function
            // We find the exact match string from earlier
            $funcString = $matches[0][0];
            $replacement = $annotation . "\n    " . ltrim($funcString);
            
            $fileContent = str_replace($funcString, $replacement, $fileContent);
        }
    }
    
    if ($fileContent !== $originalContent) {
        file_put_contents($filePath, $fileContent);
        $updatedFiles++;
        echo "Atualizado: $controllerClass\n";
    }
}

echo "Sucesso: $updatedFiles controladores atualizados!\n";
