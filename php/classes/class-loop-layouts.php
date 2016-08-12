<?php

namespace WAS;

class Loop_Layouts extends Abstract_Layout_Core {
  public $app_id = 'core';
  public $type = 'loop';

  public static $instance = null;
  public static function instance() {
    if ( is_null( self::$instance ) )
      return self::$instance = new self();
    else
      return self::$instance;
  }

  public function init() {
    add_action( 'pre_get_posts', array( $this, 'custom_query_args' ), 15 );
  }

  public function custom_query_args( $query ) {
    // $query->set( 'was_loop_id', '' );

    if ( ! $this->get_current_settings( true ) ) return;

    $id = $this->_get( 'id', false );

    $query->set( 'was_loop_id', $id );

    if ( $query_args = $this->_get( 'query_args' ) ) {
      foreach ( (array) $query_args as $key => $value ) {
        $query->set( $key, $value );
      }
    }
  }
}
