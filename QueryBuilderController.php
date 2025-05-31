
<?php
/**
 * @file
 * 
 * PHP file to build SPARQL queries based on provided JSON input.
 * 
 * This file contains the implementation for parsing JSON inputs, applying
 * filters, and constructing a dynamic SPARQL query using a custom QueryBuilder class.
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
 * Credits:
 * This project makes use of the Asparagus SPARQL Query Builder library by Benestar.
 * You can find more details at the Asparagus GitHub repository:
 * https://github.com/Benestar/asparagus
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
 */


require 'autoload.php';

use Asparagus\QueryBuilder;

class QueryBuilderController
{

    // Function to retrieve and decode JSON payload from the request
    function getJsonData()
    {
        // Get the raw POST body, which contains the JSON
        $json = file_get_contents('php://input');

        // Check if the raw input is in UTF-8 encoding
        if (!mb_check_encoding($json, 'UTF-8')) {
            // If the encoding is not UTF-8, return an error message or handle it as needed
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Invalid encoding, expected UTF-8.']);
            exit;
        }

        // Decode the JSON into an associative array
        $data = json_decode($json, true);

        // Check if the decoding was successful
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data; // Return the associative array
        } else {
            // Handle JSON decoding error
            echo json_encode(['error' => 'Invalid JSON format']);
            exit;
        }
    }

    // This function prepares an entity to be used as part of a SPARQL triple
    private function prepareTripleEntity($entity)
    {
        // If the entity is not a valid URL, return as it is, otherwise wrap it in <>
        if (filter_var($entity, FILTER_VALIDATE_URL) === FALSE) {
            return $entity;
        }
        return '<' . $entity . '>';
    }

    /**
     * Handles the conversion of JSON input into a SPARQL query.
     * 
     * This function is responsible for parsing a JSON object representing query parameters
     * and translating it into a valid SPARQL query. It supports complex features such as:
     * - Graph-based querying with subgraphs.
     * - Selectable variables.
     * - Optional relationships.
     * - Filters, including range and geospatial filters.
     * - Support for temporal and aggregate functions.
     * - Sorting and limiting query results.
     * 
     * The output is a SPARQL query returned as a JSON response.
     * 
     * @return void
     * 
     * @throws Exception If there are issues during query building or filter preparation.
     * 
     * @example JSON Input:
     * {
     *   "observables": [
     *     {
     *       "subject": "?sensor",
     *       "predicate": "http://www.w3.org/ns/sosa/madeBySensor",
     *       "object": "<http://example.org/sensor1>",
     *       "modifiers": {
     *         "limit": {
     *           "enabled": true,
     *           "value": 10
     *         }
     *       }
     *     }
     *   ],
     *   "filters": {
     *     "?sensor": [
     *       {
     *         "uri": "http://www.opengis.net/def/function/geosparql/nearby",
     *         "filters": [
     *           {
     *             "selectedFilter": { "text": "Range" },
     *             "input": { "value": "50", "expression": "lt" }
     *           }
     *         ]
     *       }
     *     ]
     *   },
     *   "sortBy": {
     *     "expression": "?sensor",
     *     "direction": "asc"
     *   },
     *   "limit": 100
     * }
     * 
     * @output JSON Response:
     * {
     *   "query": "PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
     *             SELECT ?sensor WHERE { 
     *               ?sensor <http://www.w3.org/ns/sosa/madeBySensor> <http://example.org/sensor1> .
     *               FILTER (?sensor < 50)
     *             }
     *             LIMIT 10"
     * }
     * 
     * @process
     * 1. Decodes JSON input to extract query parameters.
     * 2. Iterates through observables to build SPARQL subgraphs and apply filters.
     * 3. Handles special cases for range filters, geospatial functions, and optional triples.
     * 4. Adds temporal and aggregate functions if specified.
     * 5. Constructs the final SPARQL query with SELECT, ORDER BY, and LIMIT clauses.
     * 6. Returns the generated query as a JSON response.
     */
    public function getSparql()
    {
        // Retrieve and decode JSON input from the POST request
        $queryJson = $this->getJsonData();

        // Define common prefixes for the SPARQL query
        $prefixes = array(
            'xsd' => 'http://www.w3.org/2001/XMLSchema#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'geo' => 'http://www.opengis.net/ont/geosparql#',
        );

        // Initialize a QueryBuilder with the defined prefixes
        $queryBuilder = new QueryBuilder($prefixes);


        // Initialize structures to hold different components of the SPARQL query
        $selectable = []; // Variables to be included in the SELECT clause
        $graphs = []; // Subgraphs for SPARQL WHERE clause
        $graphsModifiers = []; // Modifiers like LIMIT or ORDER BY for subgraphs
        $functionsData = []; // Stores data for Temporal and Aggregate functions

        // Key used to identify the main "observable" entities in the JSON
        $observablesKeysIndex = "predicate";

        // Iterate through the "observables" in the JSON input
        foreach ($queryJson["observables"] as $observable) {
            // Process each key-value pair in the observable
            foreach ($observable as $key => $value) {
                // Process each key-value pair in the observable
                if ($key != "modifiers" && str_contains($observable[$key], "?")) {
                    $selectable[$observable[$key]] = true;
                }
            }

            // Determine if "subject" or "object" should be used as the key for observables
            if (array_search($observable['subject'], $queryJson["observablesKeys"]) !== false) {
                $observablesKeysIndex = "subject";
            } else if (array_search($observable['object'], $queryJson["observablesKeys"]) !== false) {
                $observablesKeysIndex = "object";
            }

            // Build a subgraph for the current observable
            $graph = $queryBuilder->newSubgraph()
                ->where(
                    $this->prepareTripleEntity($observable['subject']),
                    $this->prepareTripleEntity($observable['predicate']),
                    $this->prepareTripleEntity($observable['object'])
                );

            array_push($graphsModifiers, array()); // Initialize modifiers for this subgraph

            // Handle filters for the observable
            $tempPredicate = $queryJson["filters"][$observable[$observablesKeysIndex]];
            $unionGraphFilters = array(); //if needed like in nearby for now.

            foreach ($tempPredicate as $key2 => $value2) {

                //to make sure the name is valid and has no spaces: //causes a problem in the nearby
                if (array_key_exists("isOptional", $value2) && $value2['isOptional']) {
                    $graph->optional($observable['subject'], $this->prepareTripleEntity($key2), $value2['predicateName']);
                } else if (isset($value2['predicateName'])) {
                    $graph->also($this->prepareTripleEntity($key2), $value2['predicateName']);
                }

                // Mark variables as selectable if needed
                if ($value2['isSelectable']) {
                    $selectable[$value2['predicateName']] = true;
                }


                // Process individual filters ===================================
                $rangeFilters =  array(); // Temporary storage for range-based filters

                //loop through all filters
                foreach ($value2['filters'] as $filterKey => $filterValue) {
                    $dt = null;
                    if (isset($value2['datatype']) && isset($value2['datatype']["value"])) {
                        $dt = $value2['datatype']["value"];
                    }

                    //if filter has no name, means it is not selected, and we can't add this filter.
                    if (!array_key_exists('text', $filterValue["selectedFilter"])) {
                        continue; // So, just ignore this iteration and continue to next filter.
                    }

                    // Handle geospatial functions like "nearby" or "within"
                    if (
                        $value2['uri'] == "http://www.opengis.net/def/function/geosparql/nearby"
                        || $value2['uri'] == "http://www.opengis.net/def/function/geosparql/within"
                    ) {
                        $preparedFilter = $this->prepareFilter($filterValue, null, $dt);
                        if ($filterValue["operator"] == "UNION") {
                            array_push($unionGraphFilters, $queryBuilder->newSubgraph()->where($observable['subject'], $this->prepareTripleEntity($value2['uri']), $preparedFilter));
                        } else {
                            $graph->also($observable['subject'], $this->prepareTripleEntity($value2['uri']), $preparedFilter);
                        }
                    } else if ($filterValue["selectedFilter"]["text"] == "Range") {
                        // Handle range filters and collect them for processing
                        $preparedFilter = $this->prepareFilter($filterValue, $value2['predicateName'], $dt);
                        $rangeFilters[] = [
                            'value' => $filterValue["input"]["value"],
                            'expression' => $filterValue["input"]["expression"],
                            'preparedFilter' => $preparedFilter
                        ];
                    } else if (stripos($filterValue["selectedFilter"]["text"], "function") !== false) {
                        // Aggregate functions summarize or combine multiple values into a single result. They are commonly used for statistics like average, sum, count, min, and max.
                        // Handle functions like Temporal or Aggregate

                        // Check if 'input' value is set, not null, not empty, and not only whitespace
                        if (isset($filterValue["input"]["value"]) && trim($filterValue["input"]["value"]) !== '') {
                            // Store the value in a variable for clarity
                            $functionType = $filterValue["input"]["value"];

                            // Determine if the selected filter is temporal or date related
                            $targetKey = (
                                stripos($filterValue["selectedFilter"]["text"], "temporal") !== false
                                || stripos($filterValue["selectedFilter"]["text"], "date") !== false
                            )
                                ? 'TemporalFunctions'    // Use 'TemporalFunctions' if temporal/date is found
                                : 'AggregateFunctions';  // Otherwise, use 'AggregateFunctions'

                            // Build and add the function data to the appropriate category
                            $functionsData[$targetKey][$observable[$observablesKeysIndex]][] = [
                                'functionType'   => $functionType,                  // The function type input by the user
                                'variable_name'  => $value2['predicateName'],       // The variable's name
                                'variable_uri'   => $value2['uri'],                 // The variable's URI
                                'subject'        => $observable['subject'],         // The subject part of the observable
                                'predicate'      => $observable['predicate'],       // The predicate part of the observable
                                'object'         => $observable['object'],          // The object part of the observable
                                'fieldsetUri'    => $observable[$observablesKeysIndex] // The fieldset URI (key)
                            ];
                        }
                        // If the input value is null, empty, or only whitespace, this block is skipped.

                    } else {
                        $preparedFilter = $this->prepareFilter($filterValue, $value2['predicateName'], $dt);

                        if ($preparedFilter != null) {
                            $graph->filter($preparedFilter);
                        }
                    }
                }

                //========================== START IF RANGE FILTER =======================
                //after finish adding all filters for this property, we will check the collected range filters to solve the WrapAroundRange issue like in compass 
                //Ex: ?Direction <= "45"^^xsd:float and ?Direction >= "315"^^xsd:float, we need to use OR --> ||

                /*
                * WrapAroundRange Issue Explained:
                *
                * Some ranges, like compass directions (0° to 359°), can "wrap around" the maximum value and start again from the minimum.
                * For example, to select everything from 315° through 0° to 45°, you can't use a simple AND, because the range crosses the zero point.
                *
                * - Use AND (`&&`) when the lower limit is less than the upper limit (e.g., `> 10` && `< 15`). This is a normal, non-wrap-around interval.
                * - Use OR (`||`) when the lower limit is greater than the upper limit (e.g., `> 315` || `< 45`). This means the range wraps around the end, and covers values on both sides of the "zero" point.
                *
                * In summary:
                *   - Normal range (no wrap): AND (`&&`)
                *   - Wrap-around range: OR (`||`)
                */
                if (count($rangeFilters) > 0) {


                    // Function to sort the array based on the 'value' key
                    // usort($rangeFilters, function ($item1, $item2) {
                    //     // Convert the 'value' to integer before comparing
                    //     return (float)$item1['value'] <=> (float)$item2['value'];
                    // });

                    // Arrays to hold greater-than and less-than filter items separately.
                    $containsGreater = [];
                    $containsLess = [];

                    // Iterate through the array and separate items based on 'expression'
                    // Separate the collected range filters into "greater" (>, >=) and "less" (<, <=) expressions.
                    foreach ($rangeFilters as $item) {

                        if (strpos($item['expression'], "gt") !== false || strpos($item['expression'], ">") !== false) {

                            $containsGreater[] = $item;
                        } elseif (strpos($item['expression'], "lt") !== false || strpos($item['expression'], "<") !== false) {
                            $containsLess[] = $item;
                        }
                    }

                    $rangeFilterString = ""; // This will accumulate the combined filter expression.
                    $ORseperator = " "; // Empty initially; after the first filter, becomes " || ".

                    // For each greater-than filter, pair it with a less-than filter if available.
                    foreach ($containsGreater as $greaterItem) {
                        $dequeuedLessItem = array_shift($containsLess); // Dequeue the first item ("item1")


                        if ($dequeuedLessItem === NULL) {
                            $rangeFilterString .=   $ORseperator . $greaterItem["preparedFilter"];
                            $ORseperator = " || ";
                        } else {
                            // Generalized comparison for both numbers and dates:
                            if (((float)$dequeuedLessItem["value"] > (float)$greaterItem["value"]) || (strtotime($dequeuedLessItem["value"]) > strtotime($greaterItem["value"]))) {

                                $rangeFilterString .=  $ORseperator . " (" . $greaterItem["preparedFilter"] . ' && ' . $dequeuedLessItem["preparedFilter"] . ") ";
                                $ORseperator = " || ";
                            } else {
                                $rangeFilterString .=  $ORseperator . " (" . $greaterItem["preparedFilter"] . ' || ' . $dequeuedLessItem["preparedFilter"] . ") ";
                                $ORseperator = " || ";
                            }
                        }
                    }

                    // If there are remaining less-than filters (not paired), add them individually.
                    foreach ($containsLess as $lessItem) {
                        $rangeFilterString .= $ORseperator . $lessItem["preparedFilter"];
                        $ORseperator = " || ";
                    }
                    // Add the constructed filter string to the graph as a FILTER clause.
                    $graph->filter($rangeFilterString);
                }

                //======================= END RANGE SECTION =======================
            }

            $graph->union($unionGraphFilters);

            if (array_key_exists("modifiers", $observable) && array_key_exists("limit", $observable["modifiers"]) && $observable["modifiers"]["limit"]["enabled"] == true && !empty($observable["modifiers"]["limit"]["value"])) {
                $graphsModifiers[count($graphsModifiers) - 1]["limit"] = intval($observable["modifiers"]["limit"]["value"]);
            }


            array_push($graphs, $graph);
        }


        /**
         * If no observable is selected, but there are a map filter.
         * We will try to identify the sensors that is located within that area.
         */
        if (count($graphs) == 0 && count($queryJson["filters"]) > 0 && count($queryJson["filters"][array_key_first($queryJson["filters"])]) > 0) {

            foreach ($queryJson["filters"] as $entityName => $entityFilterValues) {
                $queryBuilder = new QueryBuilder($prefixes);
                $sensorPattern = $queryJson["sensorPattern"];
                $sensorName = $sensorPattern[$sensorPattern['sensorKey']]; //this important to know which of the sensor pattern is the sensor

                $queryBuilder->select(['(SAMPLE(' . $sensorName . ') AS ?sensorURI)', '?featureOfInterest'])
                    ->where($sensorPattern['s'], $sensorPattern['p'], $sensorPattern['o'])
                    ->where($sensorName, 'a', '<http://www.w3.org/ns/sosa/Sensor>')->optional($sensorName, '<http://www.w3.org/ns/sosa/hasFeatureOfInterest>', '?featureOfInterest')
                    ->where($sensorPattern['s'], '<http://www.w3.org/2003/01/geo/wgs84_pos#lat>', '?Latitude')
                    ->where($sensorPattern['s'], '<http://www.w3.org/2003/01/geo/wgs84_pos#long>', '?Longitude')
                    ->groupBy($sensorName, "?featureOfInterest");


                $unionGraphFilters = array(); //if needed like in nearby for now.

                //loop through all filters
                foreach ($entityFilterValues as $filterData) {
                    foreach ($filterData['filters'] as $filterKey => $filterValue) {
                        // dd($filterValue);

                        $dt = null;
                        if (isset($filterData['datatype']) && isset($filterData['datatype']["value"])) {
                            $dt = $filterData['datatype']["value"];
                        }

                        //if filter has no name, means it is not selected, and we can't add this filter.
                        if (!array_key_exists('text', $filterValue["selectedFilter"])) {
                            continue; // So, just ignore this iteration and continue to next filter.
                        }

                        if (
                            $filterData['uri'] == "http://www.opengis.net/def/function/geosparql/nearby"
                            || $filterData['uri'] == "http://www.opengis.net/def/function/geosparql/within"
                        ) {
                            $preparedFilter = $this->prepareFilter($filterValue, null, $dt);
                            if ($filterValue["operator"] == "UNION") {
                                try {
                                    array_push($unionGraphFilters, $queryBuilder->newSubgraph()->where($entityName, $this->prepareTripleEntity($filterData['uri']), $preparedFilter));
                                } catch (Exception $e) {
                                    // Catch any general exception
                                    echo 'Caught Exception: ', $e->getMessage(), "\n";
                                    echo ' | ' . $this->prepareTripleEntity($filterData['uri']) . " | " . $entityName;
                                    die($preparedFilter);
                                }
                            } else {
                                $queryBuilder->also($entityName, $this->prepareTripleEntity($filterData['uri']), $preparedFilter);
                            }
                        } else {

                            $preparedFilter = $this->prepareFilter($filterValue, $filterData['predicateName'], $dt);
                            if ($preparedFilter != null) {
                                $queryBuilder->filter($preparedFilter);
                            }
                        }
                    }
                }
            }

            $queryBuilder->union($unionGraphFilters);
            $query  = $queryBuilder->getSPARQL();


            header('Content-Type: application/json');
            echo json_encode(['query' => $query]);
            exit;
        }



        $queryBuilder->select(array_keys($selectable));


        if (array_key_exists("sortBy", $queryJson)) {
            if (array_key_exists("expression", $queryJson["sortBy"]) && array_key_exists("direction", $queryJson["sortBy"]) && $queryJson["sortBy"]['expression'] != '!none') {
                $exp = $queryJson["sortBy"]['expression'];
                if (!str_starts_with('?', $exp)) {
                    $exp = '?' . $exp;
                }
                $dir = strtoupper($queryJson["sortBy"]['direction']);
                $queryBuilder->orderBy($exp, $dir);
            }
        }

        if (array_key_exists("limit", $queryJson)) {
            $queryBuilder->limit(intval($queryJson["limit"]));
        }


        $query = "";


        if (count($graphs) == 1) {
            $queryBuilder->union($graphs);
            $query = $queryBuilder->getSPARQL(true);
        } else {
            $globalSelect = $queryBuilder->getSPARQL(true);
            $graphsAsSPARQL = $this->convertGraphsToSelect($graphs, $graphsModifiers);
            $query = str_replace("WHERE { }", "WHERE {" . $graphsAsSPARQL . "}", $globalSelect);
        }


        if (!empty($functionsData["TemporalFunctions"])) {
            $query = $this->addTemporalFunctions($functionsData["TemporalFunctions"], $query);
        }


        if (!empty($functionsData["AggregateFunctions"])) {
            // Get the aggregate selects and query
            ['aggregateSelects' => $aggregateSelects, 'query' => $aggregateQuery] = $this->addAggregateFunctionSubquery($functionsData["AggregateFunctions"]);

            // Construct replacement text and update the query
            $replacementText = " $aggregateSelects WHERE { { $aggregateQuery }";
            $query = preg_replace('/\s*WHERE\s*\{/', $replacementText, $query, 1);
        }

        // Return the query as a JSON response
        header('Content-Type: application/json');
        echo json_encode(['query' => $query]);
        exit;
    }

    /**
     * Converts a set of graphs and their respective modifiers into a SPARQL SELECT query.
     *
     * This function builds a SPARQL query by iterating through multiple graphs and applying
     * specific query modifiers (like LIMIT, ORDER BY) to each graph.
     * 
     * Example Input:
     * 
     * $graphs = [
     *     'GRAPH <http://example.org/graph1> { ?s ?p ?o }',
     *     'GRAPH <http://example.org/graph2> { ?s ?p ?o }'
     * ];
     * 
     * $graphsModifiers = [
     *     [ "limit" => 10, "orderBy" => "?s" ],
     *     [ "limit" => 5, "orderBy" => "?o" ]
     * ];
     * 
     * Example Output:
     * 
     * { GRAPH <http://example.org/graph1> { ?s ?p ?o } LIMIT 10 ORDER BY ?s }
     * UNION
     * { GRAPH <http://example.org/graph2> { ?s ?p ?o } LIMIT 5 ORDER BY ?o }
     *
     * @param array $graphs An array of graph queries to be included in the SELECT query.
     * @param array $graphsModifiers An array of query modifiers (like limit, orderBy) applied to each graph.
     * @return string The complete SPARQL query with graph unions and modifiers.
     */
    private function convertGraphsToSelect($graphs, $graphsModifiers)
    {
        $query = "";

        // Iterate through each graph using a for loop.
        // This allows accessing both $graphs and their respective modifiers in $graphsModifiers.
        for ($i = 0; $i < count($graphs); $i++) {
            $queryBuilder = new QueryBuilder();

            $queryBuilder->union($graphs[$i]);

            foreach ($graphsModifiers[$i] as $modifierName => $modifierValue) {
                $queryBuilder->{$modifierName}($modifierValue);
            }

            $query .= "{ " . $queryBuilder->getSPARQL() . " }";

            // If this is not the last graph, append the UNION keyword to join it with the next graph.
            if ($i != count($graphs) - 1) {
                $query .= " UNION ";
            }
        }

        return $query;
    }







    /**
     * Generates a SPARQL query with aggregate function subqueries.
     *
     * This function takes an array of aggregate function specifications and constructs
     * a SPARQL query with these functions applied. The function supports various types of
     * aggregate functions, such as COUNT, SUM, AVG, etc., and includes specified prefixes
     * for ensuring URI correctness.
     *
     * @param array $AggregateFunctions Array where keys are observable URIs, and values are arrays of function specifications.
     * Each function specification should include:
     * - 'functionType' (e.g., COUNT, SUM, AVG)
     * - 'variable_name' (SPARQL variable to aggregate on)
     * - 'variable_uri' (URI of the variable)
     * - 'subject' (subject of the triple pattern)
     * - 'predicate' (predicate of the triple pattern)
     * - 'object' (object of the triple pattern)
     *
     * @return array An array containing:
     * - 'aggregateSelects' => array of unique select clause strings with function aliasing
     * - 'query' => string containing the generated SPARQL query
     */
    private function addAggregateFunctionSubquery(array $AggregateFunctions)
    {
        // Prefix definitions for correct URI usage
        $prefixes = [
            'xsd' => 'http://www.w3.org/2001/XMLSchema#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'geo' => 'http://www.opengis.net/ont/geosparql#'
        ];

        // Arrays to hold SPARQL subqueries and select clause components
        $subqueryGraphs = [];
        $aggregateSelects = [];

        // Initialize QueryBuilder instance with prefixes
        $queryBuilder = new QueryBuilder($prefixes);

        // Initialize mapping of observable URIs to variable names
        $uriVariableMap = [];

        // Loop through each observable URI and its associated aggregate functions
        foreach ($AggregateFunctions as $observableURI => $aggregateFunctionsArray) {
            $graph = null;  // Initialize subquery graph

            // Initialize a set to track variable names for this URI
            $currentUriVariables = [];

            // Process each aggregate function specification
            foreach ($aggregateFunctionsArray as $functionSpec) {
                $functionType = $functionSpec['functionType'];
                $variableName = $functionSpec['variable_name'];
                $variableUri = $functionSpec['variable_uri'];
                $subject = $functionSpec['subject'];
                $predicate = $functionSpec['predicate'];
                $object = $functionSpec['object'];

                // Add the variable name to the current URI's set
                $currentUriVariables[] = $variableName;

                // Create alias for the aggregate function result
                $cleanVariable = ltrim($variableName, '?');
                $alias = "?" . ucfirst(strtolower($functionType)) . ucfirst($cleanVariable);

                // Add select clause with aggregate function and alias
                $aggregateSelects["($functionType($variableName) AS $alias)"] = true;
                $aggregateSelectNames["$alias"] = true;

                // Build or extend the graph subquery
                if (is_null($graph)) {
                    $graph = $queryBuilder->newSubgraph()
                        ->where($this->prepareTripleEntity($subject), $this->prepareTripleEntity($predicate), $this->prepareTripleEntity($object))
                        ->also($this->prepareTripleEntity($subject), $this->prepareTripleEntity($variableUri), $variableName);
                } else {
                    $graph->also($this->prepareTripleEntity($subject), $this->prepareTripleEntity($variableUri), $variableName);
                }
            }

            // Store the collected variable names for the current observable URI
            $uriVariableMap[$observableURI] = $currentUriVariables;

            // Add the completed graph to the array of subquery graphs
            $subqueryGraphs[] = $graph;
        }

        //========================================================
        // Compare variable names across different observable URIs
        $differences = [];
        $uris = array_keys($uriVariableMap);

        for ($i = 0; $i < count($uris); $i++) {
            for ($j = $i + 1; $j < count($uris); $j++) {
                $uri1 = $uris[$i];
                $uri2 = $uris[$j];

                $variables1 = $uriVariableMap[$uri1];
                $variables2 = $uriVariableMap[$uri2];

                if ($variables1 !== $variables2) {
                    $differences[] = [
                        'uri1' => $uri1,
                        'uri2' => $uri2,
                        'variables1' => $variables1,
                        'variables2' => $variables2,
                    ];
                }
            }
        }
        //============================ End Compare variable names

        // Construct the SELECT clause from unique aggregate function selects
        $textSelects = implode(" ", array_keys($aggregateSelects));
        $textSelectNames = implode(" ", array_keys($aggregateSelectNames));
        $replacement = "SELECT $textSelects";

        // Add the union of all subquery graphs to the main query builder
        $queryBuilder->union($subqueryGraphs);
        $query = $queryBuilder->getSPARQL(false);

        // Replace "SELECT *" with the constructed SELECT clause
        $finalQuery = preg_replace('/SELECT \*/', $replacement, $query, 1);

        if (!empty($differences)) {

            $finalQuery = str_ireplace('UNION', '', $finalQuery);
        }

        // Return the final query and aggregate selects
        return ["aggregateSelects" => $textSelectNames, "query" => $finalQuery];
    }

    private function addTemporalFunctions(array $TemporalFunctions, string $sparql_query)
    {
        // Loop through each observable URI and its associated functions
        foreach ($TemporalFunctions as $observableURI => $temporalFunctionsArray) {
            foreach ($temporalFunctionsArray as $functionSpec) {
                $functionType = $functionSpec['functionType'];
                $variableName = $functionSpec['variable_name'];
                // $subject = $functionSpec['subject'];
                // $predicate = $functionSpec['predicate'];


                // Create alias for the aggregate function result
                $cleanVariable = ltrim($variableName, '?');
                $alias = "?" . ucfirst(strtolower($functionType)) . ucfirst($cleanVariable);

                // Add the alias to the SELECT clause
                $sparql_query = $this->addAliasToSelect($sparql_query, $alias);

                // Add the BIND statement inside the relevant block
                $sparql_query = $this->addBindStatement($sparql_query, $functionType, $variableName, $alias, $observableURI);
            }
        }

        return $sparql_query;
    }

    private function addAliasToSelect(string $query, string $alias): string
    {
        // Find the SELECT clause and add the alias
        if (preg_match('/SELECT\s+(.*?)\s+WHERE/s', $query, $matches)) {
            $selectClause = $matches[1];

            // Avoid duplicate addition
            if (!str_contains($selectClause, $alias)) {
                $updatedSelectClause = trim($selectClause) . " " . $alias;
                $query = str_replace($selectClause, $updatedSelectClause, $query);
            }
        }
        return $query;
    }

    private function addBindStatement(
        string $sparqlQuery,
        string $functionType,
        string $variableName,
        string $alias,
        string $observableUri,
    ): string {
        $bindStatement = "BIND($functionType($variableName) AS $alias)";

        // Normalize line breaks and whitespace for easier processing
        $normalizedQuery = preg_replace('/\s+/', ' ', $sparqlQuery); // Replace multiple spaces/newlines with a single space
        $normalizedQuery = preg_replace('/\s*{\s*/', ' { ', $normalizedQuery); // Ensure space around braces
        $normalizedQuery = trim($normalizedQuery);

        // Tokenize the query into blocks split by "WHERE {"
        $blocks = preg_split('/WHERE\s*{/', $normalizedQuery);
        $modifiedQuery = $blocks[0]; // Keep the part before the first WHERE intact

        for ($i = 1; $i < count($blocks); $i++) {
            $block = "WHERE { " . $blocks[$i]; // Reconstruct each WHERE block
            $observableFound = strpos($block, $observableUri) !== false;

            if ($observableFound) {
                // Check if the bind statement already exists
                if (strpos($block, $bindStatement) === false) {
                    // Find the variable's position
                    $variablePosition = strpos($block, $variableName);
                    if ($variablePosition !== false) {
                        // Check for the OPTIONAL block with the variable
                        $optionalPattern = "/OPTIONAL\s*{[^}]*" . preg_quote($variableName) . "[^}]*}/";
                        if (preg_match($optionalPattern, $block, $matches)) {
                            // Place the BIND after the OPTIONAL block
                            $optionalBlock = $matches[0];
                            $block = str_replace($optionalBlock, $optionalBlock . " " . $bindStatement, $block);
                        } else {
                            // No OPTIONAL block, place the BIND after the variable declaration
                            $variableDeclarationPattern = "/\?\w+\s*<[^>]+>\s*" . preg_quote($variableName) . "\s*\./";
                            if (preg_match($variableDeclarationPattern, $block, $matches)) {
                                $declaration = $matches[0];
                                $block = str_replace($declaration, $declaration . " " . $bindStatement, $block);
                            }
                        }
                    }
                }
            }
            $modifiedQuery .= $block; // Append the modified block
        }

        return $modifiedQuery;
    }








    /**
     * Prepares a filter based on the given filter configuration and predicate type.
     *
     * The function constructs a formatted filter string based on the type of filter 
     * provided in the "selectedFilter" field of the $filter parameter. Different types 
     * of filters such as "nearby", "within", "contain", etc., will be processed 
     * and converted into a corresponding filter expression.
     *
     * Example Input:
     * {
     *    "filterId": "f01639269455332",
     *    "selectedFilter": {
     *        "text": "Contain",
     *        "id": "select1"
     *    },
     *    "selectedOption": {
     *        "type": "text"
     *    },
     *    "input": {
     *        "value": "test",
     *        "expression": ""
     *    }
     * }
     *
     * Example Output:
     * regex(str(?predicateName), "test", "i")
     *
     * This output is a regex filter that checks if the string form of the predicate
     * contains the value "test", case-insensitive.
     *
     * @param array $filter The filter configuration which includes filter type, input values, etc.
     * @param string $predicateName The name of the predicate to apply the filter on (e.g., ?varName in SPARQL).
     * @param string $dType The data type for the filter (e.g., xsd:string, xsd:float).
     * @return string|null The formatted filter expression or null if no valid filter is provided.
     */
    private function prepareFilter($filter, $predicateName, $dType): ?string
    {

        $formattedFilter = null;

        switch (strtolower($filter["selectedFilter"]["text"])) {
            case "nearby":
                $formattedFilter = '(' . $filter["input"]["center"]["lat"] . ' ' . $filter["input"]["center"]["lng"] . ' ' . round(($filter["input"]["radius"] / 1000), 2) . ' <http://qudt.org/vocab/unit#Kilometer>)';
                break;

            case "within":
                $correctLatLngs = "";
                foreach ($filter["input"]["latLngs"] as $key => $latLng) {
                    $correctLatLngs .= $latLng[1] . ' ' . $latLng[0]; //reorder them to Lng Lat

                    if ($key < count($filter["input"]["latLngs"]) - 1) {
                        $correctLatLngs .= ", ";
                    }
                }
                $formattedFilter = '"POLYGON((' . $correctLatLngs . '))"^^geo:wktLiteral'; //
                break;

            case "contain":
                $formattedFilter = 'regex(str(' . $predicateName . '), "' . $filter["input"]["value"] . '", "i")';
                break;
            case "bound":
                $notprefix = "";
                if (str_contains($filter["input"]["value"], "not")) {
                    $notprefix = "!";
                }
                $formattedFilter = $notprefix . 'BOUND (' . $predicateName . ')'; //BOUND (?test) or NOT: !BOUND (?test)
                break;
            case "match":
                if (str_contains($dType, 'string')) {
                    $formattedFilter = 'regex(' . $predicateName . ', "^' . $filter["input"]["value"] . '")';
                } else {
                    $formattedFilter = $predicateName . '= "' . $filter["input"]["value"] . '"^^' . $this->getXsdDataType($dType);
                }

                break;
            case "regex":
                $formattedFilter = 'regex(' . $predicateName . ', "' . $filter["input"]["value"] . '")';
                break;
            case "range" || "dateRange" || "timeRange" || "dateTimeRange":
                $formattedFilter = $predicateName . ' ' . $filter["input"]["expression"] . ' "' . $filter["input"]["value"] . '"^^' . $this->getXsdDataType($dType);
                break;
        }

        return $formattedFilter;
    }


    /**
     * Convert the URI into an XSD (XML Schema Definition) data type.
     * 
     * Example:
     * If the input URI is: http://www.w3.org/2001/XMLSchema#float,
     * the returned value will be: xsd:float.
     *
     * @param string $uri The input URI that may contain a hash or a slash.
     * @param string $prefix The prefix to append to the extracted data type (default is 'xsd').
     * @return string The data type in the format 'prefix:dataType', e.g., 'xsd:float'.
     */
    private function getXsdDataType($uri, $prefix = 'xsd')
    {
        // Assign the URI to a local variable $str, which will later be manipulated.
        $str = $uri;
        // Find the last occurrence of a hash ('#') in the URI.
        // strripos() is case-insensitive, so it works with both upper and lower case hashes.
        $hashpos = strripos($uri, "#");
        // Find the last occurrence of a slash ('/') in the URI.
        // This also uses strripos() to ensure case insensitivity.
        $slashpos = strripos($uri, "/");
        // If both a hash and a slash are found, and the hash appears after the slash:
        if ($hashpos && $slashpos && $hashpos > $slashpos) {
            // Extract the substring after the hash symbol.
            // This assumes that the hash (#) signifies the start of the relevant data type part.
            $str = substr($str, $hashpos + 1);
        }
        // Otherwise, if only a slash is found (or the slash appears after the hash):
        else if ($slashpos) {
            // Extract the substring after the last slash in the URI.
            // This is useful when the URI doesn't contain a hash, and the last part of the URI is the data type.
            $str = substr($str, $slashpos + 1);
        }
        // Return the data type prefixed with the provided $prefix (default: 'xsd').
        // This assumes that the extracted part of the URI represents a valid XSD data type.
        return $prefix . ":" . $str;
    }
}
