<?php

namespace WAS;

class Condition_Validator {
  public static $parser = null;
  public static $instance = null;
  public static $query = null;

  public static function instance() {
    if ( is_null( self::$instance ) )
      return self::$instance = new self();
    else
      return self::$instance;
  }

  private function __construct() {
    global $wp_query;

    // Grab the parenthesis parse
    if ( is_null( self::$parser ) )
      self::$parser = new \WAS\Paren_Parser();

    // Grab the query object
    if ( is_null( self::$query ) && isset( $wp_query ) )
      self::$query = $wp_query;
  }

  // Validate a condition (string or array)
  public function validate_condition( $condition ) {
    // If the conditional is a string
    if ( is_string( $condition ) ) {
      // Prepare the string into a multidimensional array via parenthesis
      $array = $this->parse_parens( $condition );

      // Recursively parse the conditions into formatted arrays
      $parsed = $this->parse_string_condition( $array );

      // Then check multiple condition arrays at once
      return $this->check_conditions( $parsed );

    // If conditional is already a valid array
    } else if ( is_array( $condition ) ) {

      // Then check multiple condition arrays at once
      return $this->check_conditions( $condition );
    }

    return false;
  }

  // Check multiple condition arrays at once
  public function check_conditions( $conditions = array() ) {
    $i = -1;
    $result = null;
    $logical = false;

    foreach ( (array) $conditions as $key => $condition ) {
      $i++;
      if ( is_array( $condition ) ) {
        if ( array_key_exists( 'var', $condition ) ) {
          $value = $this->validate( $condition );
        } else {
          $value = $this->check_conditions( $condition );
        }
      } else if ( is_string( $condition ) && in_array( $condition, array( 'OR', 'AND' ) ) ) {
        $logical = $condition;
      }
      
      if ( $logical && ! is_null( $result ) ) {
        if ( $logical == 'AND' ) $result = ( $result && $value );
        if ( $logical == 'OR' ) $result = ( $result || $value );
      } else {
        $result = $value;
      }
    }
    return $result;
  }

  // Validate a "conditional" array
  public function validate( $condition = array() ) {
    global $post, $wp_query;

    // If this is not an array
    if ( is_string( $condition ) ) {
      // Make it a formatted "conditional" arrays
      $condition = $this->convert_string_condition( $condition );
    }

    $default_condition = array(
      'var'      => '',
      'var_type' => 'function',
      'params'   => array(),
      'operator' => '==',
      'value'    => true,
    );

    // Parse args against defaults
    $condition = wp_parse_args( $condition, $default_condition );

    extract( $condition );

    // Prepare the var
    // function checks
    if ( $var_type == 'function' ) {
      if ( ! function_exists( $var ) ) 
        return new \WP_Error( 'no_function_exists', sprintf( 'Your function %s doesn\'t exists or you have an error in your conditional.', $var ), $condition );

      $var = call_user_func_array( $var, $params );
      
    // variable checks
    } else if ( $var_type == 'variable' ) {
      $var = $var;

    // post object checks
    } else if ( is_object( $post ) && $var_type == 'post_property' ) {
      if ( ! property_exists( $post, $var ) ) 
        return new \WP_Error( 'no_post_property_exists', sprintf( 'Your post property %s doesn\'t exists or you have an error in your conditional.', $var ) );

      $var = ( property_exists( $post, $var ) ) ? $post->$var : null;

    // wp_query var checks
    } else if ( is_object( $wp_query ) && $var_type == 'wp_query_var' ) {
      if ( ! isset( $wp_query->query_vars[ $var ] ) ) 
        return new \WP_Error( 'no_query_var_exists', sprintf( 'Your query var %s doesn\'t exists or you have an error in your conditional.', $var ) );

      $var = ( isset( $wp_query->query_vars[ $var ] ) ) ? $wp_query->get( $var ) : null;

    // wp_query property checks
    } else if ( is_object( $wp_query ) && $var_type == 'wp_query_property' ) {
      if ( ! property_exists( $wp_query, $var ) ) 
        return new \WP_Error( 'no_query_property_exists', sprintf( 'Your query property %s doesn\'t exists or you have an error in your conditional.', $var ) );

      $var = $wp_query->$var;
    // wp_query method checks
    } else if ( is_object( $wp_query ) && $var_type == 'wp_query_method' ) {
      if ( ! method_exists( $wp_query, $var ) ) 
        return new \WP_Error( 'no_query_method_exists', sprintf( 'Your query method %s doesn\'t exists or you have an error in your conditional.', $var ) );

      $var = call_user_func_array( array( $wp_query, $var ), $params );
    }

    // Compare the var to the value
    if ( $operator == "==" ) {
      $match = ( $var === $value );
    } else if ( $operator == "!=" ) {
      $match = ( $var !== $value );
    }

    // Return the boolean
    return $match;
  }

  // Prepare a string condition by parsing it via nested parenthesis
  public function parse_parens( $string ) {
    // Wrap it in a set of parentheses in order to force the parser
    $result = self::$parser->parse( sprintf( "%s", $string ) );

    // If the result is empty, then still pass an array with the original string
    $result = ( ! empty( $result ) ) ? $result : (array) $string;
    return $result;
  }

  // Recursively parse arrays of parenthetical sets
  public function parse_string_condition( $array ) {
    static $run_times = 0;
    $run_times++;

    // Get rid of empty items
    $array = array_filter( (array) $array );

    // For each set of parentheses...
    foreach ( $array as $key => $item ) {

      // If it is an array, then do it again...
      if ( is_array( $item ) ) {
        $array[ $key ] = $this->parse_string_condition( $item );

      // Else format it into a "conditional" array 
      } else {
        $array[ $key ] = $this->convert_string_condition( $item );
      }
    }

    // Return the newly formatted array of "conditionals" and any logical operands
    return $array;
  }

  // Convert a string in to a "conditional" array
  public function convert_string_condition( $passed = '' ) {

    // If only a logical operand was passed, then just return it...
    if ( in_array( trim( strtolower( $passed ) ), array( 'and', 'or' ) ) ) {
      return trim( $passed );
    }

    // If there are any logical operands, then wrap phrases with parenthesis and re-parse...
    if ( strpos( $passed, "AND" ) !== false || strpos( $passed, "OR" ) !== false ) {
      $formatted = sprintf( '((%s))', str_replace( "OR", ") OR (", str_replace( "AND", ") AND (", $passed ) ) );
      return $this->parse_string_condition( $this->parse_parens( $formatted ) );
    }

    $var_type = 'function';
    $var = '';
    $operator = '==';
    $value = true;
    $left = '';
    $right = '';
    $string = $passed;

    // If only true is passed, then we know what to do here...
    if ( trim( $passed ) == 'true' ) {
      $var_type = 'variable';
      $var = true;
      return compact( 'var', 'var_type', 'operator', 'value' );
    }

    // Grab the operator and split string
    // the operators must be in this order because if < is before <= then
    // "less than or equal to" would become "< or equal to" (which would not parse)
    $operators = array(
      '<=' => [ 'less than or equal to', 'is less than or equal to', 'lesser than or equal to', 'is lesser than or equal to' ],
      '>=' => [ 'greater than or equal to', 'is greater than or equal to' ],
      '<' =>  [ 'less than', 'is less than', 'lesser than', 'is lesser than' ],
      '>' =>  [ 'greater than', 'is greater than' ],
      '!=' => [ 'is not', 'isnt', 'isn\'t', 'does not equal', 'doesnt equal', 'doesn\'t equal', 'not' ],
      '==' => [ 'is', 'equals', 'is equal to' ],
    );
    foreach ( $operators as $logical => $phrases ) {

      // Sort phrases by length to avoid conflicts (e.g. "is" should not be checked until "is greater than" has been parsed)
      usort( $phrases, array( $this, 'sort_by_word_count' ) );

      // Wrap the phrase with spaces to avoid processing function names like "is_home"
      $phrases = array_map( array( $this, 'wrap_with_spaces' ), $phrases );

      // Replace phrases with the logical operand
      $string = str_replace( $phrases, $logical, $string );

      // If operator is present
      if ( strpos( $string, $logical ) !== false ) {

        // Split into left and right
        $halves = explode( $logical, $string );

        // There must be 2 halves of the string
        if ( count( $halves ) !== 2 ) {
          return new \WP_Error( 'invalid_conditional_string', 'Your conditional string is in an invalid format', $passed );
        }

        // Set values
        $operator = $logical;
        $left = $halves[0];
        $right = $halves[1];
        break;
      }
    }

    // Valid var_types
    $var_types = array( 'function', 'post_property', 'variable', 'wp_query_var', 'wp_query_property', 'wp_query_method' );
    foreach ( $var_types as $i => $type ) {

      // If a var type is present
      if ( strpos( trim( $left ), $type ) !== false ) {

        // Set and break
        $var_type = $type;
        break;
      }
    }

    // Remove the var_type from the string 
    $var = trim( str_replace( $var_type, '', $left ) );
    if ( $var_type == 'variable' ) {
      $var = $this->format_data_type( $var );
    }

    // Value is on the right side or is just plain...
    $value = ( $right ) ? trim( $right ) : $value;
    $value = $this->format_data_type( $value );

    // Prepare the formatted "conditional" array
    $result_array = compact( 'var', 'var_type', 'operator', 'value' );

    return $result_array;
  }

  // Format data types from strings
  public function format_data_type( $data ) {
    $data = ( $data === 'true' ) ? true : $data;
    $data = ( $data === 'false' ) ? false : $data;
    $data = ( is_numeric( $data ) ) ? (int) $data : $data;
    return $data;
  }

  // Compare by word count
  public function sort_by_word_count( $a, $b ) {
    return count( explode( ' ', $b ) ) - count( explode( ' ', $a ) );
  }

  // Wrap string with spaces
  public function wrap_with_spaces( $string ) {
    return ' ' . $string . ' ';
  }
}
