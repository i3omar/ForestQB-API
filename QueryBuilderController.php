
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

    // This function handles the POST request to convert JSON into a SPARQL query
    public function getSparql()
    {
        // Call the function and get the decoded JSON
        $queryJson = $this->getJsonData();

        $prefixes = array(
            'xsd' => 'http://www.w3.org/2001/XMLSchema#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'geo' => 'http://www.opengis.net/ont/geosparql#',
        );

        $queryBuilder = new QueryBuilder($prefixes);


        $selectable = [];
        $graphs = [];
        $graphsModifiers = []; //this will contain the inner query limit, or order by

        $observablesKeysIndex = "predicate";

        foreach ($queryJson["observables"] as $observable) {
            foreach ($observable as $key => $value) {
                if ($key != "modifiers" && str_contains($observable[$key], "?")) {
                    $selectable[$observable[$key]] = true;
                }
            }

            if (array_search($observable['subject'], $queryJson["observablesKeys"]) !== false) {
                $observablesKeysIndex = "subject";
            } else if (array_search($observable['object'], $queryJson["observablesKeys"]) !== false) {
                $observablesKeysIndex = "object";
            }

            $graph = $queryBuilder->newSubgraph()
                ->where($this->prepareTripleEntity($observable['subject']),  $this->prepareTripleEntity($observable['predicate']), $this->prepareTripleEntity($observable['object']));
            array_push($graphsModifiers, array());

            $tempPredicate = $queryJson["filters"][$observable[$observablesKeysIndex]];
            $unionGraphFilters = array(); //if needed like in nearby for now.

            foreach ($tempPredicate as $key2 => $value2) {

                //to make sure the name is valid and has no spaces: //cases a problem in the nearby

                if (array_key_exists("isOptional", $value2) && $value2['isOptional']) {
                    $graph->optional($observable['subject'], $this->prepareTripleEntity($key2), $value2['predicateName']);
                } else if (isset($value2['predicateName'])) {
                    $graph->also($this->prepareTripleEntity($key2), $value2['predicateName']);
                }

                if ($value2['isSelectable']) {
                    $selectable[$value2['predicateName']] = true;
                }



                $rangeFilters =  array(); //set rangeFilters to be empty.

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
                        $preparedFilter = $this->prepareFilter($filterValue, $value2['predicateName'], $dt);
                        $rangeFilters[] = ['value' => $filterValue["input"]["value"], 'expression' => $filterValue["input"]["expression"], 'preparedFilter' => $preparedFilter];
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

                if (count($rangeFilters) > 0) {


                    // Function to sort the array based on the 'value' key
                    // usort($rangeFilters, function ($item1, $item2) {
                    //     // Convert the 'value' to integer before comparing
                    //     return (float)$item1['value'] <=> (float)$item2['value'];
                    // });

                    // Arrays to hold separated items
                    $containsGreater = [];
                    $containsLess = [];

                    // Iterate through the array and separate items based on 'expression'
                    foreach ($rangeFilters as $item) {

                        if (strpos($item['expression'], "gt") !== false || strpos($item['expression'], ">") !== false) {

                            $containsGreater[] = $item;
                        } elseif (strpos($item['expression'], "lt") !== false || strpos($item['expression'], "<") !== false) {
                            $containsLess[] = $item;
                        }
                    }

                    $rangeFilterString = "";
                    $ORseperator = " "; //empty unless we added group, we change it to " || "

                    foreach ($containsGreater as $greaterItem) {
                        $dequeuedLessItem = array_shift($containsLess); // Dequeue the first item ("item1")


                        if ($dequeuedLessItem === NULL) {
                            $rangeFilterString .=   $ORseperator . $greaterItem["preparedFilter"];
                            $ORseperator = " || ";
                        } else {
                            if ((float)$dequeuedLessItem["value"] > (float)$greaterItem["value"]) {
                                $rangeFilterString .=  $ORseperator . " (" . $greaterItem["preparedFilter"] . ' && ' . $dequeuedLessItem["preparedFilter"] . ") ";
                                $ORseperator = " || ";
                            } else {
                                $rangeFilterString .=  $ORseperator . " (" . $greaterItem["preparedFilter"] . ' || ' . $dequeuedLessItem["preparedFilter"] . ") ";
                                $ORseperator = " || ";
                            }
                        }
                    }
                    foreach ($containsLess as $lessItem) {
                        $rangeFilterString .= $ORseperator . $lessItem["preparedFilter"];
                        $ORseperator = " || ";
                    }

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
