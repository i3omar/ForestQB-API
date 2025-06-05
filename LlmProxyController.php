
<?php

/**
 * @file
 * 
 * This file contains the implementation of the `LlmProxyController` class, which is responsible 
 * for LLM connection.
 * 
 * @package    ForestQB API
 * @author     OMAR MUSSA
 * @copyright  Copyright (c) 2022-2025 OMAR MUSSA
 * @license    https://opensource.org/licenses/MIT MIT License
 * @version    1.0.0
 * @link       https://github.com/i3omar/ForestQB
 * 
 * SPDX-License-Identifier: MIT
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * 
 * 
 * 
 * 
 * 
 * Make sure to:
 * 1. Create a `.env` file in the root of your project.
 * 2. Store the sensitive information in the `.env` file.
 * 3. Load the `.env` file using the `phpdotenv` library.
 * 
 * Example `.env` file:
 * 
 * 
 * LLM_API_BASE_URI=https://api.openai.com
 * LLM_API_KEY=your_llm_api_key
 * LLM_API_URL_SUFFIX=/v1/chat/completions
 * 
 * Note: The `.env` file should never be committed to version control (e.g., Git). Make sure to add it to your `.gitignore`.
 */

require_once __DIR__ . '/vendor/autoload.php';  // Autoload dependencies like vlucas/phpdotenv

use Dotenv\Dotenv;

use Qdrant\Qdrant;
use Qdrant\Models\Request\CreateCollection;
use Qdrant\Models\Request\VectorParams;
use Qdrant\Http\Builder;
use Qdrant\Config;


use Qdrant\Models\PointsStruct;
use Qdrant\Models\PointStruct;
use Qdrant\Models\VectorStruct;

use Qdrant\Models\Request\SearchRequest;


// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();


class LlmProxyController
{
    private static $llmInfo = [];


    /**
     * Constructor to load environment variables and initialize the llmInfo property.
     */
    public function __construct()
    {
        // Load environment variables from the .env file
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        // Initialize the $llmInfo array using values from environment variables
        self::$llmInfo = [
            // LLM Api Data from env
            'baseURL' => $_ENV['LLM_API_BASE_URI'], //ex: https://api.openai.com
            'key' => $_ENV['LLM_API_KEY'],
            'urlSuffix' => $_ENV['LLM_API_URL_SUFFIX'], //ex: /v1/chat/completions
            'qdrantURL' => $_ENV['QDRANT_URL'], //ex: http://localhost:6333
        ];
    }
    /**
     * Handle the incoming request and forward it to the specified endpoint.
     * 
     * This method replicates the functionality of Laravel's Http::post() using cURL.
     *
     * @return string The response body from the proxied request.
     */
    public function index()
    {
        // Base URI of the remote API
        $baseUri = self::$llmInfo['baseURL'];
        $key = self::$llmInfo['key'];
        $urlSuffix = self::$llmInfo['urlSuffix'];

        $headers = [
            'Authorization: Bearer ' . $key,
            // You can override Content-Type if needed here
        ];

        // Prepare the data from the POST request
        $postData = json_decode(file_get_contents('php://input'), true);

        try {
            // Perform the POST request using cURL
            $response = $this->makePostRequest($baseUri . $urlSuffix, $postData, $headers, false);

            /// Output the response
            header('Content-Type: application/json');
            echo $response;
        } catch (Exception $e) {
            // Handle error
            // Return error as JSON
            header('Content-Type: application/json', true, 500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function createCollection()
    {
        // $config = new Config('http://localhost:6333');
        $config = new Config(self::$llmInfo['qdrantURL']);
        // $config->setApiKey('your_qdrant_api_key'); // Optional

        $transport = (new Builder())->build($config);
        $client = new Qdrant($transport);

        // Prepare the data from the POST request
        $data = json_decode(file_get_contents('php://input'), true);

        $embeddingsData = self::createEmbeddings($data["rdfTriples"]);
        $collectionName = $data["collectionName"]; //"rdf_ontology";

        try {
            $createCollection = new CreateCollection();
            $createCollection->addVector(new VectorParams($embeddingsData["dimension"], VectorParams::DISTANCE_COSINE), 'triple');
            // $createCollection->addVector(new VectorParams($embeddingsData["dimension"], VectorParams::DISTANCE_DOT), 'triple');
            $response = $client->collections($collectionName)->create($createCollection);
        } catch (\Qdrant\Exception\InvalidArgumentException $e) {
            // Handle the exception
            header('Content-Type: application/json', true, 500);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }


        $points = new PointsStruct();
        foreach ($embeddingsData["embeddings"] as $key => $value) {
            $points->addPoint(
                new PointStruct(
                    $key,
                    new VectorStruct($value, 'triple'),
                )
            );
        }
        $client->collections($collectionName)->points()->upsert($points, ['wait' => 'true']);


        http_response_code(200); // Set status code (optional, 200 is default)
        header('Content-Type: application/json'); // Set header
        echo json_encode(['message' => 'OK, the index was successfully created']);
    }


    public function searchRequest()
    {

        // $config = new Config('http://localhost:6333');
        $config = new Config(self::$llmInfo['qdrantURL']);
        // $config->setApiKey('your_qdrant_api_key'); // Optional

        $transport = (new Builder())->build($config);
        $client = new Qdrant($transport);

        // Prepare the data from the POST request
        $data = json_decode(file_get_contents('php://input'), true);

        // Param #1
        $collectionName = $data["collectionName"]; //"rdf_ontology";
        // Param #2
        $limit = isset($data["limit"]) ? intval($data["limit"]) : 0;
        if ($limit <= 0) {
            $limit = 20;
        }
        // Param #3
        $query = $data["query"];

        // create the equavlent query vector
        $queryVector = self::createEmbeddings($query)["embeddings"][0];

        $searchRequest = (new SearchRequest(new VectorStruct($queryVector, 'triple')))
            ->setLimit($limit)
            ->setParams([
                'hnsw_ef' => 128,
                'exact' => false,
            ])
            ->setWithPayload(false);

        $response = $client->collections($collectionName)->points()->search($searchRequest);

        http_response_code(200); // Set status code (optional, 200 is default)
        header('Content-Type: application/json'); // Set header
        echo json_encode($response["result"]);
    }



    private function createEmbeddings($sentencesArray)
    {
        $embeddingServer = "http://localhost:5007/embedding";

        $headers = [
            // You can override Content-Type if needed here
        ];

        try {
            // Perform the POST request using cURL
            $embeddingResponse = $this->makePostRequest($embeddingServer, $sentencesArray, $headers, false);
            return json_decode($embeddingResponse, true);
        } catch (Exception $error) {
            // Handle error
            // echo $e->getMessage();
            throw $error;
        }
    }

    /**
     * Sends a generic HTTP POST request using cURL.
     *
     * @param string $url        The target URL for the POST request.
     * @param array|string $postData The data to send in the POST body. If an array is provided, it will be JSON-encoded.
     * @param array $headers     An array of additional headers (each header as a string, e.g., "Authorization: Bearer ...").
     * @param bool $verifySSL    Whether to verify the SSL certificate (default: false). Set to false for self-signed certs.
     *
     * @throws Exception If the cURL request fails.
     *
     * @return string The raw response from the server.
     *
     * @example
     * $url = 'https://api.example.com/v1/resource';
     * $data = ['name' => 'John'];
     * $headers = ['Authorization: Bearer YOUR_TOKEN'];
     * $response = $this->makePostRequest($url, $data, $headers, false);
     * $result = json_decode($response, true);
     */
    private function makePostRequest($url, $postData = [], $headers = [], $verifySSL = false)
    {
        // Initialize cURL
        $ch = curl_init($url);

        // Ensure $postData is a JSON string
        if (!is_string($postData)) {
            $postData = json_encode($postData);
        }

        // Default headers
        $defaultHeaders = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postData)
        ];

        // Merge any custom headers (your custom headers can override defaults)
        $allHeaders = array_merge($defaultHeaders, $headers);

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);

        // Execute the request and get the response
        $response = curl_exec($ch);

        // Error handling
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL error: $error");
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Close the cURL session
        curl_close($ch);

        // Optional: check HTTP status code for error
        if ($http_code < 200 || $http_code >= 300) {
            throw new Exception("HTTP error code: $http_code");
        }

        return $response;
    }
}
