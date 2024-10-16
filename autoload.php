<?php
/**
 * Autoload function for dynamically loading PHP classes from the Asparagus library.
 * 
 * The Asparagus library is a SPARQL abstraction layer for PHP, designed to simplify
 * the creation and execution of SPARQL queries. This autoloader automatically includes
 * the necessary class files from the library without requiring manual `include` or `require` statements.
 * 
 * We are loading classes from the Asparagus library, specifically from the `/asparagus/src/`
 * directory where all the core files of the library are located. This library provides
 * functionality such as building SPARQL queries using a PHP query builder.
 * 
 * The library is available on GitHub: https://github.com/Benestar/asparagus
 * 
 * How it works:
 * 1. The `$baseDir` variable points to the base directory where the Asparagus library classes are stored.
 *    In this case, it is set to `/asparagus/src/`, which contains the core library files.
 * 2. When a class (e.g., `Asparagus\QueryBuilder`) is used but hasn't been included yet, this autoloader
 *    converts the class name into a file path by replacing the namespace backslashes (`\`) with directory 
 *    separators (`/`) and appends `.php`.
 * 3. The resulting file path is then checked to see if it exists, and if found, the class file is included
 *    using `require`.
 * 4. If the file does not exist, an error message is displayed to help debug the issue.
 * 
 * This approach ensures that all necessary classes from the Asparagus library are automatically loaded,
 * making it easier to use the library's SPARQL query-building functionality without manual inclusion.
 */
spl_autoload_register(function ($class) {
    // Define the base directory to point to /asparagus/src
    $baseDir = __DIR__ . '/QueryBuilder/src/';
    
    // Replace namespace separators with directory separators, append .php
    $file = $baseDir . str_replace('\\', '/', $class) . '.php';
    
    // Check if the file exists and require it
    if (file_exists($file)) {
        require $file;
    } else {
        echo "File not found: $file";
    }
});
