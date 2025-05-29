# ForestQB-API: A Middleware for JSON to SPARQL Conversion and Secure API Access

Welcome to the **ForestQB API**, a middleware designed to work seamlessly with the **ForestQB app**. The primary purpose of this API is to handle JSON-based queries from the app, convert them into appropriate SPARQL queries, and facilitate secure data retrieval from private endpoints. This API ensures that your sensitive credentials are hidden from the client, provides authentication for private APIs, and proxies communication with ForestBot, our chatbot agent.

## Key Features

### 1. **JSON to SPARQL Query Conversion**
The ForestQB API serves as a crucial component in converting JSON queries into well-structured SPARQL queries. The **ForestQB app** relies on this conversion to retrieve data correctly from the linked data sources. When the app sends a query in JSON format, this API parses the JSON, transforms it into SPARQL, and returns the appropriate results.

For example, if the app sends a JSON query like:
```json
{
  "subject": "?person",
  "predicate": "foaf:knows",
  "object": "?friend"
}
```
The API will convert this into a SPARQL query:
```sparql
SELECT ?person WHERE { ?person foaf:knows ?friend }
```

For more information on the JSON query structure, refer to: [JSON_QUERY_STRUCTURE.md](docs/JSON_QUERY_STRUCTURE.md)

### 2. **Middleware for Private API Access**
ForestQB API acts as a middleware to **hide sensitive credentials** such as the username and password from the app. If your data source does not have a public API, or you wish to protect sensitive credentials, this API provides a secure way to authenticate and mirror requests.

- **Authentication Process**: The API securely stores the credentials in environment variables (`.env` file) and handles the authentication process when making requests to your private API. This ensures that your sensitive information is never exposed to the client.
- **Private API Mirroring**: The API mirrors your private API and exposes it as if it were a public endpoint. This allows your app to interact with your private API securely and seamlessly.

For example, when the app makes a request:
```
POST /sparql
{
  "query": "SELECT * WHERE { ?s ?p ?o }"
}
```
The API will authenticate the request, forward it to your private SPARQL endpoint, and return the results to the app.

### 3. **Proxy to Communicate with ForestBot**
The API also serves as a **proxy** for communication with **ForestBot**, our intelligent chatbot agent. When the app needs to interact with ForestBot, this API forwards the requests and responses, ensuring smooth communication between the app and the bot.

The proxy ensures that all interactions with ForestBot are handled securely and effectively, allowing the ForestQB app to leverage the chatbot's functionalities, such as answering questions, retrieving data, or interacting with users.

## How It Works

1. **JSON Query Handling**:
    - The app sends a JSON query to the `/getSparql` endpoint.
    - The API converts the JSON query into SPARQL using the `QueryBuilderController`.
    - The converted SPARQL query is sent to the appropriate private endpoint for data retrieval.
    - The API returns the SPARQL results back to the app.
    
    For more information on the JSON query structure, refer to: [JSON_QUERY_STRUCTURE.md](docs/JSON_QUERY_STRUCTURE.md)

2. **Private API Proxy**:
    - The app sends a query to the `/sparql` endpoint.
    - The API authenticates using credentials stored in the `.env` file.
    - The query is forwarded to the private SPARQL endpoint.
    - The API mirrors the request and response, ensuring that the app interacts with the private API as if it were public.

3. **ForestBot Proxy**:
    - The app sends a message to the `/forestBot/proxy` endpoint.
    - The API proxies this request to ForestBot, the chatbot agent.
    - ForestBot processes the message and the response is returned to the app.

## Example Usage

1. **Convert JSON to SPARQL**:
   ```
   POST /getSparql
   Content-Type: application/json

   {
     "subject": "?person",
     "predicate": "foaf:knows",
     "object": "?friend"
   }
   ```
   - This converts the JSON into the SPARQL query: `SELECT ?person WHERE { ?person foaf:knows ?friend }`.

2. **Private API Proxy**:
   ```
   POST /sparql
   Content-Type: application/json

   {
     "query": "SELECT * WHERE { ?s ?p ?o }"
   }
   ```
   - The API will authenticate, forward the query to the private endpoint, and return the results.

3. **Communicating with ForestBot**:
   ```
   POST /forestBot/proxy
   Content-Type: application/json

   {
     "sender": "user",
     "message": "Hello, ForestBot!"
   }
   ```
   - The API will proxy this request to ForestBot, and the chatbot's response will be returned to the app.

## Setup Instructions

1. Clone the repository and install dependencies:
   ```bash
   git clone https://github.com/i3omar/ForestQB-API.git
   cd ForestQB-API
   composer install
   ```

2. Create an `.env` file in the root of your project:
   ```ini
   DATABASE_URL_PREFIX=https://api.example.com/v1/
   DATABASE_NAME=my_database
   DATABASE_URL_SUFFIX=/query
   DATABASE_USERNAME=my_username
   DATABASE_PASSWORD=my_password
   ```

3. Run the API using the built-in PHP server:

    When you run the API using the built-in PHP server, you specify a port where the server will listen for incoming requests. In the following example, the port is set to `8081`:
    
    ```bash
    php -S localhost:8081 -t public
    ```
    
    The `localhost:8081` part defines the server's address and port:
    
    * `localhost`: This specifies that the server will only be accessible from your local machine.
    * `8081`: This is the port number the server listens to for incoming requests.
    
    #### Changing the Port:
    
    If you wish to change the port number (for example, to avoid conflicts with other services or to meet a specific requirement), you can modify the port number in the command. For instance:
    
    ```bash
    php -S localhost:8082 -t public
    ```
    
    This would start the server on port `8082` instead of `8081`.
    
    #### Important: Synchronize with ForestQB Tool Settings
    
    If you change the port number, **ensure that the ForestQB app or any other tools you're using to interact with the API are configured to match the new port**. Otherwise, communication will fail as the tool won't be able to reach the server.

4. For the [ForestBot](https://github.com/i3omar/ForestBot) Agent:

   We use the `ForestBotProxyController.php` file as a proxy for the RASA chat agent.
   By default, the RASA webhook endpoint is:
    ```bash
    http://localhost:5005/webhooks/rest/webhook
    ```
    In the code, this is set as:
    ```php
    // Base URI of the remote API
    $baseUri = 'http://localhost:5005';
    $endpoint = '/webhooks/rest/webhook';
    ```
    If you change the RASA server's port or URL, make sure to update the values of `$baseUri` and `$endpoint` accordingly to reflect the correct port and URL.


   
6. Finally, you need to run [ForestQB](https://github.com/i3omar/ForestQB) app.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for more details.