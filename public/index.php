<?php

/**
 * @file
 * 
 * A simple PHP-based router to handle various API requests. This file is responsible
 * for routing POST requests to specific controllers based on the request URI.
 * It also sets appropriate CORS headers to allow cross-origin requests.
 * 
 * Usage:
 * You can run this script using the built-in PHP server:
 * 
 *     php -S localhost:8081 -t public
 * 
 * Routes:
 * - POST /sparql               -> Handles SPARQL queries via `EndpointController`.
 * - POST /getSparql            -> Converts a JSON-based query into SPARQL using `QueryBuilderController`.
 * - POST /forestBot/proxy      -> Forwards the request to `ForestBotProxyController` to proxy external requests.
 * 
 * CORS:
 * The script allows cross-origin requests by setting CORS headers for all incoming requests.
 * It also handles preflight (OPTIONS) requests by sending a 200 OK response.
 * 
 * Example Usage:
 * 
 *     curl -X POST http://localhost:8081/sparql \
 *     -H "Content-Type: application/json" \
 *     -d '{"query": "SELECT * WHERE { ?s ?p ?o }"}'
 * 
 * For `getSparql`, you can send a JSON query structure that will be converted into a SPARQL query.
 * 
 * Example JSON for `/getSparql`:
 * 
 *     curl -X POST http://localhost:8081/getSparql \
 *     -H "Content-Type: application/json" \
 *     -d '{
 *           "subject": "?person",
 *           "predicate": "foaf:knows",
 *           "object": "?friend"
 *         }'
 * 
 * This will convert the JSON into a corresponding SPARQL query like:
 * 
 *     SELECT ?person WHERE { ?person foaf:knows ?friend }
 * 
 * @package    ForestQB API
 * @author     OMAR MUSSA
 * @copyright  Copyright (c) 2024 OMAR MUSSA
 * @license    https://opensource.org/licenses/MIT MIT License
 * @version    1.0.0
 * @link       https://github.com/i3omar/ForestQB
 * 
 * SPDX-License-Identifier: MIT
 * 
 * Note: The included `.env` file should be properly configured for database credentials, and make sure
 * the `.env` file is not committed to version control by adding it to `.gitignore`.
 */

// Enable CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle OPTIONS request for preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Retrieve request URI and method
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];


// Route the requests based on URI and method
if ($requestMethod === 'POST' && $requestUri === '/sparql') {
    // Route to EndpointController
    require_once __DIR__ . '/../EndpointController.php';
    $controller = new EndpointController();
    $controller->index();
} elseif ($requestMethod === 'POST' && $requestUri === '/getSparql') {
    // Route to QueryBuilderController for converting JSON to SPARQL
    require_once __DIR__ . '/../QueryBuilderController.php';
    $controller = new QueryBuilderController();
    $controller->getSparql();
} elseif ($requestMethod === 'POST' && $requestUri === '/forestBot/proxy') {
    // Route to ForestBotProxyController
    require_once __DIR__ . '/../ForestBotProxyController.php';
    $controller = new ForestBotProxyController();
    $controller->index();
} elseif ($requestMethod === 'POST' && $requestUri === '/forestBot/llm/proxy') {
    // Route to LlmProxyController
    require_once __DIR__ . '/../LlmProxyController.php';
    $controller = new LlmProxyController();
    $controller->llmProxy();
} elseif ($requestMethod === 'POST' && $requestUri === '/forestBot/llm/collection/build') {
    // Route to LlmProxyController
    require_once __DIR__ . '/../LlmProxyController.php';
    $controller = new LlmProxyController();
    $controller->createCollection();
} elseif ($requestMethod === 'POST' && $requestUri === '/forestBot/llm/collection/search') {
    // Route to LlmProxyController
    require_once __DIR__ . '/../LlmProxyController.php';
    $controller = new LlmProxyController();
    $controller->searchRequest();
}else {
    // Return 404 error if the route is not found
    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);
}
