<?php

namespace WAS;

class Theme_Customizations {
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

  private function __construct( $app = false ) {
    if ( is_null( $this->app ) )
      $this->app = $app;

    if ( $this->app ) {
      add_filter( 'stylesheet_uri', array( $this, 'override_stylesheet' ) );
      add_action( 'in_admin_footer', array( $this, 'iris_palette' ) );
      add_filter( 'tiny_mce_before_init', array( $this, 'tiny_mce_init' ) );
    }

    $this->theme_support();
  }

  // Theme support
  public function theme_support() {
    $support = $this->app->_get( 'config/theme/theme_support' );
    if ( $support ) foreach ( $support as $key => $value ) {
      add_theme_support( $key, $value );
    }
  }

  // Override stylesheet for gulp build files
  public function override_stylesheet( $stylesheet ) {
    if ( $this->app->is_theme() && ! is_admin() && file_exists( $this->app->dir( 'css' ) . 'app.min.css' ) )
      return $this->app->url( 'css' ) . 'app.min.css' ;
    else if ( $this->app->is_theme() && ! is_admin() && file_exists( $this->app->dir( 'css' ) . 'app.css' ) )
      return $this->app->url( 'css' ) . 'app.css' ;

    return $stylesheet;
  }

  // Override default iris palette
  public function iris_palette() {
    $palette = $this->app->_get( 'config/theme/palette' );

    if ( $palette ) {
      $palette = json_encode( array_values( $palette ) );
      echo "
        <script>
          jQuery(function($) {
              if (typeof $.wp !== 'undefined' && typeof $.wp.wpColorPicker !== 'undefined') {
                $.wp.wpColorPicker.prototype.options = {
                  // add your custom website or brand colours here
                  palettes: " . $palette . "
                };
              }
          });
        </script>
      ";
    }
  }

  // Override default TinyMCE Palette
  public function tiny_mce_init( $init ) {
    $palette = $this->app->_get( 'config/theme/palette' );
    if ( $palette ) {
      $custom_colors = array();
      foreach ( $palette as $name => $hex ) {
        $custom_colors[] = str_replace( '#', '', $hex );
        $custom_colors[] = ucwords( $name );
      }

      $init['textcolor_map'] = json_encode( $custom_colors );
    }

    $fonts = $this->app->_get( 'config/theme/font_families' );
    if ( $fonts ) {
      $custom_fonts = array();
      foreach ( $fonts as $name => $family ) {
        $custom_fonts[] = $name . '=' . $family;
      }

      $init['font_formats'] = implode( ';', $custom_fonts );
    }

    $sizes = $this->app->_get( 'config/theme/font_sizes' );
    if ( $sizes ) {
      $init['fontsize_formats'] = implode( ' ', $sizes );
    }

    $heights = $this->app->_get( 'config/theme/line_heights' );
    if ( $heights ) {
      $init['lineheight_formats'] = implode( ' ', $heights );
    }

    return $init;
  }

}
