<?php

namespace Asparagus;

use InvalidArgumentException;

/**
 * Package-private class to validate expressions like variables and IRIs.
 *
 * @license GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class ExpressionValidator {

	/**
	 * Accept all expressions
	 */
	const VALIDATE_ALL = 255;

	/**
	 * Accept variables
	 */
	const VALIDATE_VARIABLE = 1;

	/**
	 * Accept IRIs
	 */
	const VALIDATE_IRI = 2;

	/**
	 * Accept prefixes
	 */
	const VALIDATE_PREFIX = 4;

	/**
	 * Accept prefixed IRIs
	 */
	const VALIDATE_PREFIXED_IRI = 8;

	/**
	 * Accept native values
	 */
	const VALIDATE_NATIVE = 16;

	/**
	 * Accepts property paths
	 */
	const VALIDATE_PATH = 32;

	/**
	 * Accept functions
	 */
	const VALIDATE_FUNCTION = 64;

	/**
	 * Accept functions with variable assignments
	 */
	const VALIDATE_FUNCTION_AS = 128;

	/**
     * Accept complex expressions that mix native values and full IRIs
     * For example: (5.4635725784415 117.9058464244 12.99 <http://qudt.org/vocab/unit#Kilometer>)
     */
    const VALIDATE_COMPLEX_EXPRESSION = 512;  // New constant for complex expressions

	/**
     * Accept POLYGON Well-Known Text (WKT) Literals.
     */
    const VALIDATE_POLYGON = 1024;  // New constant specifically for POLYGON literals

	/**
     * Accept comparison expressions like ?Variable > "30"^^xsd:float.
     */
    const VALIDATE_COMPARISON = 2048;  // New constant for comparison expressions

	/**
     * Accept nested comparison expressions like (?Variable1 > "value"^^type || ?Variable2 < "value"^^type).
     */
    const VALIDATE_NESTED_COMPARISON = 4096;



	/**
	 * @var RegexHelper
	 */
	private $regexHelper;

	public function __construct() {
		$this->regexHelper = new RegexHelper();
	}

	/**
	 * Validates the given expression and tracks it.
	 * VALIDATE_PREFIX won't track prefixes.
	 *
	 * @param string $expression
	 * @param int $options
	 * @throws InvalidArgumentException
	 */
	public function validate( $expression, $options ) {
		if ( !is_string( $expression ) ) {
			throw new InvalidArgumentException( '$expression has to be a string.' );
		}

		if ( !$this->matches( $expression, $options ) ) {
			throw new InvalidArgumentException( '$expression has to be a ' .
				implode( ' or a ', $this->getOptionNames( $options ) ) . ', got ' . $expression
			);
		}
	}

	private function getOptionNames( $options ) {
		$names = array(
			'variable' => self::VALIDATE_VARIABLE,
			'IRI' => self::VALIDATE_IRI,
			'prefix' => self::VALIDATE_PREFIX,
			'prefixed IRI' => self::VALIDATE_PREFIXED_IRI,
			'native' => self::VALIDATE_NATIVE,
			'path' => self::VALIDATE_PATH,
			'function' => self::VALIDATE_FUNCTION,
			'function with variable assignment' => self::VALIDATE_FUNCTION_AS,
			'complex expression' => self::VALIDATE_COMPLEX_EXPRESSION,  // Added name for complex expression validation
			'POLYGON literal' => self::VALIDATE_POLYGON,  // Changed to POLYGON literal validation
			'comparison expression' => self::VALIDATE_COMPARISON,  // Added name for comparison expression validation
            'nested comparison' => self::VALIDATE_NESTED_COMPARISON,  // Added name for nested comparison validation


		);

		$names = array_filter( $names, function( $key ) use ( $options ) {
			return $options & $key;
		} );

		return array_keys( $names );
	}

	private function matches( $expression, $options ) {
		return $this->isVariable( $expression, $options ) ||
			$this->isIRI( $expression, $options ) ||
			$this->isPrefix( $expression, $options ) ||
			$this->isPrefixedIri( $expression, $options ) ||
			$this->isValue( $expression, $options ) ||
			$this->isPath( $expression, $options ) ||
			$this->isFunction( $expression, $options ) ||
			$this->isFunctionAs( $expression, $options ) ||
			$this->isComplexExpression( $expression, $options ) ||  // New check for nearby etc (i3omar) - Added check for complex expressions
			$this->isPolygon( $expression, $options ) ||  // to check for POLYGON literals
			$this->isComparison( $expression, $options ) ||  // Added check for comparison expressions
			$this->isNestedComparison( $expression, $options );  // Added check for nested comparisons




	}

	/**
 * Validates nested comparison expressions like:
 * (?Variable1 > "30"^^xsd:float || ?Variable2 < "100"^^xsd:integer || ?Variable3 = "50"^^xsd:integer).
 *
 * @param string $expression The expression to validate
 * @param int $options The validation options
 * @return bool True if the expression matches the nested comparison pattern
 */
private function isNestedComparison( $expression, $options ) {
    return $options & self::VALIDATE_NESTED_COMPARISON &&
           $this->regexHelper->matchesRegex(
               '^\s*\(\s*(\\?[A-Za-z_][A-Za-z0-9_]*\s*(=|<|>|<=|>=)\s*".+?"\^\^xsd:[a-zA-Z]+)(\s*(\|\||&&)\s*\\?[A-Za-z_][A-Za-z0-9_]*\s*(=|<|>|<=|>=)\s*".+?"\^\^xsd:[a-zA-Z]+)*\s*\)\s*$',
               $expression
           );
}

	/**
     * Validates comparison expressions like ?Variable > "30"^^xsd:float.
     *
     * @param string $expression The expression to validate
     * @param int $options The validation options
     * @return bool True if the expression matches the comparison pattern
     */
    private function isComparison( $expression, $options ) {
        // Check if the VALIDATE_COMPARISON option is set and if the expression matches the comparison pattern
        return $options & self::VALIDATE_COMPARISON &&
               $this->regexHelper->matchesRegex(
				'^\s*\?[A-Za-z_][A-Za-z0-9_]*\s*(=|<|>|<=|>=)\s*"\d+(\.\d+)?"\s*\^\^xsd:[a-zA-Z]+\s*$', 
				$expression
               );
    }

	/**
     * Validates POLYGON Well-Known Text (WKT) literals.
     * Example: "POLYGON((117.46719360352 5.5689609255762, ...))"^^geo:wktLiteral
     *
     * @param string $expression The expression to validate
     * @param int $options The validation options
     * @return bool True if the expression matches the POLYGON pattern
     */
    private function isPolygon( $expression, $options ) {
        // Check if the VALIDATE_POLYGON option is set and if the expression matches the POLYGON pattern
        return $options & self::VALIDATE_POLYGON &&
               $this->regexHelper->matchesRegex(
                   '"POLYGON\(\(([\d\.]+\s+[\d\.]+,?\s*)+\)\)"\^\^geo:wktLiteral', 
                   $expression
               );
    }

	/**
     * Validates complex expressions with multiple native values followed by a full IRI.
     * Example: (number number number <IRI>)
     *
     * @param string $expression The expression to validate
     * @param int $options The validation options
     * @return bool True if the expression matches the complex pattern
     */
	private function isComplexExpression( $expression, $options ) {
		// Check for a pattern like (value value value <IRI>)
		return $options & self::VALIDATE_NATIVE &&
			   $this->regexHelper->matchesRegex( '\(\s*\d+(\.\d+)?\s+\d+(\.\d+)?\s+\d+(\.\d+)?\s+<[^>]+>\s*\)', $expression );
	}

	
	private function isVariable( $expression, $options ) {
		return $options & self::VALIDATE_VARIABLE &&
			$this->regexHelper->matchesRegex( '\{variable}', $expression );
	}

	private function isIRI( $expression, $options ) {
		return $options & self::VALIDATE_IRI &&
			$this->regexHelper->matchesRegex( '\{iri}', $expression );
	}

	private function isPrefix( $expression, $options ) {
		return $options & self::VALIDATE_PREFIX &&
			$this->regexHelper->matchesRegex( '\{prefix}', $expression );
	}

	private function isPrefixedIri( $expression, $options ) {
		return $options & self::VALIDATE_PREFIXED_IRI &&
			$this->regexHelper->matchesRegex( '\{prefixed_iri}', $expression );
	}

	private function isValue( $expression, $options ) {
		return $options & self::VALIDATE_NATIVE &&
			$this->regexHelper->matchesRegex( '\{native}', $expression );
	}

	private function isPath( $expression, $options ) {
		return $options & self::VALIDATE_PATH &&
			$this->regexHelper->matchesRegex( '\{path}', $expression );
	}

	private function isFunction( $expression, $options ) {
		// @todo this might not be complete
		return $options & self::VALIDATE_FUNCTION &&
			$this->regexHelper->matchesRegex( '\{function}', $expression ) &&
			$this->checkBrackets( $expression );
	}

	private function checkBrackets( $expression ) {
		$expression = $this->regexHelper->escapeSequences( $expression );
		return substr_count( $expression, '(' ) === substr_count( $expression, ')' );
	}

	private function isFunctionAs( $expression, $options ) {
		return $options & self::VALIDATE_FUNCTION_AS &&
			$this->regexHelper->matchesRegex( '\(\{function} AS \{variable}\)', $expression );
	}

}
