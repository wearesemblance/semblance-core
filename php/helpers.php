<?php

namespace WAS;

// Register app
function register_app( $id = false, $settings = array() ) {
  // Return if we cannot get the source file
  if ( ! $trace = debug_backtrace() ) return false;
  $file = $trace[0]['file'];

  // Register the app with some settings and a file path
  return App::instance( $id, $settings, $file );
}

// Get an app
function get_app( $id = false ) {

  // Grab the core
  global $was_core;

  // We must have an id to get an app
  if ( $id )
    return $was_core->get_app( $id );

  // ... else return false
  return false;
}

// Get theme app instance
function get_theme_app() {
  global $was_core;
  return $was_core->get_theme_app();
}

// Encode an object
function encode_object( $object, $method = 'post' ) {
  if ( $method == 'get' )
    return $object->encoded_object = urlencode( gzcompress( serialize( $object ), -1 ) );
  if ( $method == 'post' )
    return $object->encoded_object = base64_encode( gzcompress( serialize( $object ), -1 ) );
}

// Decode an object
function decode_object( $object_string, $method = 'post' ) {
  if ( $method == 'get' )
    return unserialize( gzuncompress( urldecode( $object_string ) ) );
  if ( $method == 'post' )
    return unserialize( gzuncompress( base64_decode( $object_string ) ) );
}

// Convert an array to stdClass
function array_to_object( $array ) {
    return json_decode( json_encode( $array ) );
}

// Create a data-ajax-args value
function ajax_data_atts( $object = '', $callback = '', $args = array() ) {
  $atts = array(
    'object' => $object,
    'callback' => $callback,
    'args' => $args,
  );

  if ( ! empty( $atts['object'] ) && is_object( $atts['object'] ) )
      $atts['object'] = encode_object( $atts['object'] );

  return encode_object( (object) $atts );
}

// Backup genesis_parse_attr function
function genesis_parse_attr( $context, $attributes = array() ) {
  if ( function_exists( '\genesis_parse_attr' ) ) {
    return \genesis_parse_attr( $context, $attributes );
  }

  $defaults = array(
    'class' => sanitize_html_class( $context ),
  );

  $attributes = wp_parse_args( $attributes, $defaults );

  //* Contextual filter
  return apply_filters( "genesis_attr_{$context}", $attributes, $context );
}

// Backup genesis_attr function
function genesis_attr( $context, $attributes = array() ) {
  if ( function_exists( '\genesis_attr' ) ) {
    return \genesis_attr( $context, $attributes );
  }

  $attributes = \WAS\genesis_parse_attr( $context, $attributes );

  $output = '';

  //* Cycle through attributes, build tag attribute string
  foreach ( $attributes as $key => $value ) {
    if ( ! $value ) {
      continue;
    }

    if ( true === $value ) {
      $output .= esc_html( $key ) . ' ';
    } else {
      $output .= sprintf( '%s="%s" ', esc_html( $key ), esc_attr( $value ) );
    }
  }

  $output = apply_filters( "genesis_attr_{$context}_output", $output, $attributes, $context );

  return trim( $output );
}

// Get loop id from loop layout
function get_the_loop_id() {
  return get_query_var( 'was_loop_id' );
}

// Helper for is_singular() inside pre_get_posts
function pre_is_singular( $post_type = '' ) { 
  global $wp_query;

  if ( $post_type == 'post' ) {
    return (
      $wp_query->is_singular() 
      && ! $wp_query->get( 'post_type' ) 
      && ! $wp_query->is_page() 
      && ! $wp_query->is_attachment() 
    );
  }

  return (
    ! $wp_query->is_archive() 
    && $wp_query->is_singular()
    && in_array( $wp_query->get( 'post_type' ), (array) $post_type )
    && ! $wp_query->is_page() 
    && ! $wp_query->is_attachment() 
  );
}