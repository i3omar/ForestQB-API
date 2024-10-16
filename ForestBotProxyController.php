
<?php

/**
 * This class responsible for the RASA chatbot connection.
 */
class ForestBotProxyController
{

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
        $baseUri = 'http://localhost:5005';
        $endpoint = '/webhooks/rest/webhook';

        // Prepare the data from the POST request
        $postData = file_get_contents('php://input');

        // Perform the POST request using cURL
        $response = $this->makePostRequest($baseUri . $endpoint, $postData);

        /// Output the response
        header('Content-Type: application/json');
        echo $response;
    }

    /**
     * Make a POST request using cURL.
     *
     * @param string $url The URL to make the POST request to.
     * @param string $postData The data to send in the POST request.
     * @return string The response body from the cURL request.
     */
    private function makePostRequest($url, $postData)
    {
        // Initialize cURL
        $ch = curl_init($url);

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json', // Assuming you're sending JSON data
            'Content-Length: ' . strlen($postData)
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Set to false if you're using a self-signed certificate

        // Execute the request and get the response
        $response = curl_exec($ch);

        // Close the cURL session
        curl_close($ch);

        return $response;
    }
}
