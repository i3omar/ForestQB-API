<?php

/**
 * @file
 * 
 * This file contains the implementation of the `EndpointController` class, which is responsible 
 * for interacting with a remote SPARQL endpoint. It constructs database queries, authenticates 
 * with credentials, and sends SPARQL queries to the endpoint using cURL.
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
 * DATABASE_URL_PREFIX=https://api.example.com/v1/
 * DATABASE_NAME=your_database_name
 * DATABASE_URL_SUFFIX=/query
 * DATABASE_USERNAME=your_api_username
 * DATABASE_PASSWORD=your_secure_password
 * 
 * URL Structure Example:
 * https://api.example.com/v1/[DATABASE_NAME][DATABASE_URL_SUFFIX]
 * 
 * Note: The `.env` file should never be committed to version control (e.g., Git). Make sure to add it to your `.gitignore`.
 */

require_once __DIR__ . '/vendor/autoload.php';  // Autoload dependencies like vlucas/phpdotenv

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

class EndpointController
{
  
   

    // Private static property to hold database configurations
    private static $dbInfo = [];

    /**
     * Constructor to load environment variables and initialize the dbInfo property.
     */
    public function __construct()
    {
        // Load environment variables from the .env file
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        // Initialize the $dbInfo array using values from environment variables
        self::$dbInfo = [
            0 => [ // Main endpoint from env
                'databaseURLPrefix' => $_ENV['DATABASE_URL_PREFIX'],
                'databaseName' => $_ENV['DATABASE_NAME'],
                'databaseURLSuffix' => $_ENV['DATABASE_URL_SUFFIX'],
                'username' => $_ENV['DATABASE_USERNAME'],
                'password' => $_ENV['DATABASE_PASSWORD'],
            ],
            1 => [ // Another endpoint (can be configured later)
                "databaseURLPrefix" => "",
                "databaseName" => "",
                "databaseURLSuffix" => "",
                "username" => "",
                "password" => "",
            ]
        ];
    }

    /**
     * Get the full database URL by combining the prefix, name, and suffix.
     *
     * @param int $index Index of the database in the $dbInfo array.
     * @return string Full database URL.
     */
    public static function getDatabaseURL($index)
    {
        if (!isset(self::$dbInfo[$index])) {
            throw new \Exception("Database configuration at index $index not found.");
        }
        return self::$dbInfo[$index]["databaseURLPrefix"] . self::$dbInfo[$index]["databaseName"] . self::$dbInfo[$index]["databaseURLSuffix"];
    }

    /**
     * Query the SPARQL endpoint with the provided query and credentials.
     *
     * @param array $request The server request data.
     * @param string $query The SPARQL query string.
     * @param int $index The index of the database configuration to use (default: 0).
     * @return string JSON-encoded response from the SPARQL endpoint.
     */
    public static function queryEndpoint($request, $query, $index = 0)
    {
        if (!array_key_exists($index, self::$dbInfo)) {
            $index = 0;
        }

        $username = self::$dbInfo[$index]['username'];
        $password = self::$dbInfo[$index]['password'];
        $url = self::getDatabaseURL($index);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url . '?query=' . urlencode($query));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/sparql-results+json'
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    /**
     * Main controller method to handle incoming requests.
     *
     * This method processes incoming JSON requests, retrieves the SPARQL query and index,
     * and sends the query to the configured endpoint.
     */
    public function index()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $query = $data['query'] ?? '';

        // Assuming index is passed as part of the request payload
        $index = isset($data['index']) ? intval($data['index']) : 0;

        $response = self::queryEndpoint($_SERVER, $query, $index);

        header('Content-Type: application/json');
        echo $response;
    }
}
