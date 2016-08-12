<?php

namespace WAS;

class ACF_Builder {
  public $app = null;

  public static $instances = array();
  public static function instance( $app = false ) {
    if ( ! $app || ! property_exists( $app, 'id' ) )
      return;

    $key = $app->id;

    if ( ! array_key_exists( $key, self::$instances ) )
      return self::$instances[ $key ] = new self( $app );
    else
      return self::$instances[ $key ];
  }

  public function __construct( $app ) {
    if ( is_null( $this->app ) )
      $this->app = $app;

    $file = $app->dir( 'acf' ) . 'field-groups.php';
    if ( file_exists( $file ) ) {
      $this->app = $app;
      add_filter( 'acf/settings/path', array( $this, 'acf_settings_path' ) );
      add_filter( 'acf/settings/dir', array( $this, 'acf_settings_dir' ) );
      add_filter( 'acf/settings/show_admin', '__return_false' );
      include_once( get_stylesheet_directory() . '/acf/acf.php' );
    }
  }

  public function acf_settings_path( $path ) {
    $path = $this->dir( 'acf' );
    return $path;
  }

  public function acf_settings_dir( $dir ) {
    $dir = $this->url( 'acf' );
    return $dir;
  }
}