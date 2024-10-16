
### The JSON input structure for `getSparql()` in `QueryBuilderController.php`

1. **Purpose of the Method**:  
   The `getSparql()` method parses a JSON input from the request body, builds a SPARQL query using a `QueryBuilder`, and constructs a series of `graphs` and `modifiers` based on the parsed data. This SPARQL query will include different graph patterns, filters, and optional modifiers like `LIMIT` and `ORDER BY`.

2. **Key Components**:
   - **Prefixes**: Predefined namespaces like `xsd`, `rdfs`, and `geo` are used within the SPARQL query.
   - **Observables**: Each observable is processed to create a graph pattern. The `subject`, `predicate`, and `object` of each observable are used to form triples.
   - **Modifiers**: Modifiers such as `LIMIT` and `ORDER BY` are applied to individual graphs if they are specified in the JSON input.
   - **Filters**: The function supports various filters applied to the observable predicates, such as spatial filters (`nearby`, `within`) and others specified by the user.

### Generic JSON Input Documentation

The JSON input is expected to contain the following key fields, which the function uses to build the SPARQL query:

```json
{
  "observables": [
    {
      "subject": "<entity>",
      "predicate": "<relationship>",
      "object": "<entity>",
      "modifiers": {
        "limit": {
          "enabled": true,
          "value": 10
        },
        "orderBy": {
          "enabled": true,
          "value": "?predicate"
        }
      }
    }
  ],
  "observablesKeys": ["predicate", "subject", "object"],
  "filters": {
    "<observableKey>": [
      {
        "predicateName": "?predicate",
        "uri": "<uri>",
        "isOptional": false,
        "isSelectable": true,
        "filters": [
          {
            "selectedFilter": {
              "text": "Contain",
              "id": "select1"
            },
            "input": {
              "value": "test",
              "expression": ""
            },
            "operator": "AND"
          }
        ],
        "datatype": {
          "value": "xsd:string"
        }
      }
    ]
  }
}
```

### Breakdown of the JSON Input Fields

1. **`observables`** (required):
   - This is an array of **observables** representing the basic graph patterns for the SPARQL query.
   - Each observable contains:
     - `subject`: The subject of the triple pattern.
     - `predicate`: The predicate (relationship) of the triple pattern.
     - `object`: The object of the triple pattern.
     - `modifiers`: An optional field to apply query modifiers like `LIMIT` and `ORDER BY`.
       - `limit`: 
         - `enabled`: Boolean indicating if the `LIMIT` should be applied.
         - `value`: The integer value for the `LIMIT` modifier.
       - `orderBy`:
         - `enabled`: Boolean indicating if the `ORDER BY` should be applied.
         - `value`: The variable on which the ordering should occur (e.g., `?predicate`).

   Example:
   ```json
   "observables": [
     {
       "subject": "?person",
       "predicate": "foaf:knows",
       "object": "?friend",
       "modifiers": {
         "limit": {
           "enabled": true,
           "value": 10
         },
         "orderBy": {
           "enabled": true,
           "value": "?person"
         }
       }
     }
   ]
   ```

2. **`observablesKeys`** (optional):
   - This is an array of keys that can be either `subject`, `predicate`, or `object`. It helps determine which part of the observable (subject, predicate, or object) will be used as the filter key.

   Example:
   ```json
   "observablesKeys": ["subject", "predicate"]
   ```

3. **`filters`** (optional):
   - This is a nested structure where filters are applied to specific observables.
   - The outer key (`<observableKey>`) corresponds to either `subject`, `predicate`, or `object` from the observable, depending on what is specified in `observablesKeys`.
   - Each filter contains:
     - `predicateName`: The variable name for the predicate in the SPARQL query.
     - `uri`: The URI of the function or predicate being filtered, such as `geo:nearby`.
     - `isOptional`: Boolean indicating if the predicate should be optional in the query.
     - `isSelectable`: Boolean indicating if this filter should be part of the `SELECT` clause.
     - `filters`: An array of filters applied to the predicate. Each filter contains:
       - `selectedFilter`: The selected filter, with:
         - `text`: The type of filter (e.g., "Contain", "Nearby", "Within").
         - `id`: A unique identifier for the filter.
       - `input`: The input for the filter, including:
         - `value`: The value to be used in the filter.
         - `expression`: Additional expressions for range filters.
       - `operator`: Logical operator for combining filters (`AND`, `OR`, `UNION`).
     - `datatype`: Specifies the data type of the filter (e.g., `xsd:string`, `xsd:float`).

   Example:
   ```json
   "filters": {
     "?person": [
       {
         "predicateName": "?name",
         "uri": "http://xmlns.com/foaf/0.1/name",
         "isOptional": false,
         "isSelectable": true,
         "filters": [
           {
             "selectedFilter": {
               "text": "Contain",
               "id": "filter1"
             },
             "input": {
               "value": "John",
               "expression": ""
             },
             "operator": "AND"
           }
         ],
         "datatype": {
           "value": "xsd:string"
         }
       }
     ]
   }
   ```

### Additional Considerations:

- **Spatial Filters**:  
  The `uri` field within the `filters` structure can include spatial filters such as:
  - `geo:nearby`: A spatial filter to find entities that are nearby a specific location.
  - `geo:within`: A spatial filter to find entities that are within a specific area.

- **Optional Filters**:  
  If the `isOptional` field is `true`, the triple pattern will be wrapped in an `OPTIONAL` clause in SPARQL, allowing for more flexible querying.

### Example of Full JSON Input:

```json
{
  "observables": [
    {
      "subject": "?person",
      "predicate": "foaf:knows",
      "object": "?friend",
      "modifiers": {
        "limit": {
          "enabled": true,
          "value": 10
        },
        "orderBy": {
          "enabled": true,
          "value": "?person"
        }
      }
    }
  ],
  "observablesKeys": ["subject", "predicate"],
  "filters": {
    "?person": [
      {
        "predicateName": "?name",
        "uri": "http://xmlns.com/foaf/0.1/name",
        "isOptional": false,
        "isSelectable": true,
        "filters": [
          {
            "selectedFilter": {
              "text": "Contain",
              "id": "filter1"
            },
            "input": {
              "value": "John",
              "expression": ""
            },
            "operator": "AND"
          }
        ],
        "datatype": {
          "value": "xsd:string"
        }
      }
    ]
  }
}
```

### Summary:
The JSON input provides all the necessary information for constructing a SPARQL query, including observable patterns, filters, and query modifiers. The structure is flexible, allowing you to build complex queries with filters, optional clauses, and spatial relationships.