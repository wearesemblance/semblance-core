<?php

namespace WAS;

abstract class Abstract_Layout_Core {
  public static $parser = null;

  public $app_id = 'core';
  public $app = null;
  public $type = '';
  public $layouts = array();
  public $defaults = null;
  public $valid_layouts = null;
  public $current_settings = null;

  // Constructor
  public function __construct() {
    // Store the app
    $this->app = \WAS\get_app( $this->app_id );
    
    // Grab the parenthesis parse
    if ( is_null( self::$parser ) )
      self::$parser = new \WAS\Paren_Parser();

    // Set up the layouts
    $this->set_up_layouts();

    $this->app->add_action( sprintf( '%s_layouts_init', $this->type ), array( $this, 'init' ) );
    $this->app->do_action( sprintf( '%s_layouts_init', $this->type ), $this );
  }

  // Getter
  public function _get( $passed = '' ) {
    $keys = explode( '/', $passed );
    $val = ( empty( $keys ) ) ? null : $this->get_current_settings();
    $defaults = $this->get_defaults();

    if ( is_single() || is_page() ) {
      if ( $meta = get_post_meta( get_the_ID(), '_' . $this->type . '_layout_' . $keys[0], true ) ) {
        if ( $meta == 'on' ) return true;
        if ( $meta == 'off' ) return true;
      }
    }

    foreach ( (array) $keys as $i => $key ) {

      if ( is_object( $val ) && property_exists( $val, $key ) )
        $val = $val->$key;
      else if ( is_array( $val ) && array_key_exists( $key, $val ) )
        $val = $val[ $key ];
      else
        return null;

      if ( $i == 0 && is_array( $defaults[ $key ] ) )
        $val = wp_parse_args( $val, $defaults[ $key ] );
    }
    return $val;
  }

  // Set up layouts
  public function set_up_layouts() {
    // Grab layouts via filter
    $this->layouts = $this->app->apply_filters( $this->type . '_layouts', array() );
  }

  // Register layout
  public function register_layout( $key = '', $layout = array() ) {
    // if ( $this->get_layout( $key ) )
    //   return false;

    return true;
  }

  // Get default settings
  public function get_defaults() {
    if ( is_null( $this->defaults ) )
      $this->defaults = $this->app->load_config( sprintf( '%s-layout-defaults', $this->type ) );

    return $this->defaults;
  }

  // Get array of layouts
  public function get_layouts() {
    return $this->app->apply_filters( $this->type . '_layouts', (array) $this->layouts );
  }

  // // Get layout by key
  // public function get_layout( $key = '' ) {
  //   if ( $key && ! $this->layout_is_registered() )
  //     return false;

  //   return $this->layouts[ $key ];
  // }

  // // Check if layout is registered
  // public function layout_is_registered( $key = '' ) {
  //   return ( array_key_exists( $key, $this->layouts ) );
  // }

  // Init
  public function init() {}

  // Get valid layouts
  public function get_valid_layouts( $refresh = false ) {
    if ( ! is_null( $this->valid_layouts ) && ! $refresh )
      return $this->valid_layouts;
    
    $this->valid_layouts = array();

    // For each registered layout
    foreach ( $this->get_layouts() as $layout ) {

      // Add it to valid layouts if validation passes
      if ( $this->layout_is_valid( $layout ) ) {
        $this->valid_layouts[] = $layout;
      }
    }

    return $this->valid_layouts;
  }

  // Grab the current settings for the loop
  public function get_current_settings( $refresh = false ) {
    if ( ! is_null( $this->current_settings ) && ! $refresh )
      return $this->current_settings;

    // Create array of settings from valid layouts
    $valid_settings = array_map( array( $this, 'collect_settings' ), (array) $this->get_valid_layouts( $refresh ) );

    usort( $valid_settings, array( $this, 'sort_by_priority' ) );

    $valid_settings = $this->app->apply_filters( 'pre_' . $this->type . '_layout_settings', $valid_settings );

    // Then merge them into one array 
    if ( $valid_settings )
      $valid_settings = call_user_func_array( 'array_merge', (array) $valid_settings );

    // Recursively parse settings
    $this->current_settings = $this->parse_settings( $valid_settings, $this->get_defaults() );

    return $this->current_settings;
  }

  public function sort_by_priority( $a, $b ) {
    return intval( $b['priority'] ) - intval( $a['priority'] );
  }

  // Recursively parse settings
  public function parse_settings( &$a, $b ) {
    $a = (array) $a;
    $b = (array) $b;
    $result = $b;
    foreach ( $a as $k => &$v ) {
      if ( is_array( $v ) && isset( $result[ $k ] ) ) {
        $result[ $k ] = $this->parse_settings( $v, $result[ $k ] );
      } else {
        $result[ $k ] = $v;
      }
    }
    return $result;
  }

  // Collect the settings from a layout
  public function collect_settings( $layout ) {
    if ( ! array_key_exists( 'priority', $layout['settings'] ) )
      $layout['settings']['priority'] = 10;

    return $layout['settings'];
  }

  // Check if layout is valid
  public function layout_is_valid( $layout ) {
    $parser = \WAS\Condition_Validator::instance();
    return $parser->validate_condition( $layout['condition'] );
  }

  // Dynamic action handler
  public function add_or_remove( $key, $action, $original_callback, $priority = 10, $custom_callback = '' ) {

    if ( ! $custom_callback && ( ! is_int( $priority ) && ! is_float( $priority ) ) ) {
      $custom_callback = $priority;
      $priority = 10;
    } else if ( ! $custom_callback ) {
      $custom_callback = $original_callback;
    }

    // Remove the element
    remove_action( $action, $original_callback, $priority );

    // If element has custom position
    $elements = $this->_get( 'elements' );
    $is_active = $this->_get( $key );
    $is_active = ( is_string( $is_active ) && $is_active === 'true' ) ? true : $is_active;
    if ( $elements && array_key_exists( $key, $elements ) ) {

      // If element is turned on and is an element custom position is an array
      if ( $is_active && is_array( $elements[ $key ] ) ) {

        // The priority is the second item in the array
        $priority = ( isset( $elements[ $key ][1] ) ) ? $elements[ $key ][1] : 10;

        // Add element to custom position
        add_action( $elements[ $key ][0], $custom_callback, $priority );
      }

    // If element does not have a custom position and is turned on
    } else if ( $is_active ) {

      // Add element
      add_action( $action, $custom_callback, $priority );
    }

    // Keep element removed otherwise...
  }

  // Dynamic action handler resetter - for undoing add_or_remove
  public function remove_or_add( $key, $action, $original_callback, $priority = 10, $custom_callback = '' ) {
    
    if ( ! $custom_callback && ( ! is_int( $priority ) && ! is_float( $priority ) ) ) {
      $custom_callback = $priority;
      $priority = 10;
    } else if ( ! $custom_callback ) {
      $custom_callback = $original_callback;
    }

    // Re-add the element
    add_action( $action, $original_callback, $priority );

    // If element has custom position
    $elements = $this->_get( 'elements' );
    $is_active = $this->_get( $key );
    $is_active = ( is_string( $is_active ) ) ? ( $is_active === 'true' ) : $is_active;

    if ( $elements && array_key_exists( $key, $elements ) ) {

      // If element is turned on and is an element custom position is an array
      if ( $is_active && is_array( $elements[ $key ] ) ) {

        // The priority is the second item in the array
        $priority = ( isset( $elements[ $key ][1] ) ) ? $elements[ $key ][1] : 10;

        // Remove the element from the custom position
        remove_action( $elements[ $key ][0], $custom_callback, $priority );
      }

    // If element does not have a custom position 
    } else if ( $is_active ) {

      // Remove element
      remove_action( $action, $custom_callback, $priority );
    }

    // Keep element turned on otherwise...
  }
}



