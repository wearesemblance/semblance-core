<?php

namespace WAS;

if ( ! class_exists( '\WAS\Options_Page' ) ) {

  class Options_Page {

    public $app;
    public $page;
    public $options_page;

    public static $instances = array();
    public static function instance( $page ) {
      if ( ! $page || ! array_key_exists( 'id', $page ) )
        return;

      $key = $page['id'];

      if ( ! array_key_exists( $key, self::$instances ) )
        return self::$instances[ $key ] = new self( $page );
      else
        return self::$instances[ $key ];
    }

    private function __construct( $page ) {
      $defaults = array(
        'id' => '',
        'parent_slug' => '',
        'page_title' => '',
        'menu_title' => '',
        'capability' => 'manage_options',
        'cmb2_id' => '',
      );

      $this->page = wp_parse_args( $page, $defaults );
      $this->app = $page['_app'];

      add_action( 'admin_init', array( $this, 'init' ) );
      add_action( 'admin_menu', array( $this, 'add_options_page' ) );
    }

    public function init() {
      register_setting( $this->page['id'], $this->page['id'] );
    }

    public function add_options_page() {
      $menu_title = ( ! empty( $this->page['menu_title'] ) ) ? $this->page['menu_title'] : $this->page['page_title'];

      if ( ! empty( $this->page['parent_slug'] ) ) {
        $this->options_page = add_submenu_page( $this->page['parent_slug'], $this->page['page_title'], $menu_title, $this->page['capability'], $this->page['id'], array( $this, 'admin_page_display' ) );
      } else {
        $this->options_page = add_menu_page( $this->page['page_title'], $menu_title, $this->page['capability'], $this->page['id'], array( $this, 'admin_page_display' ) );
      }

      if ( class_exists( 'CMB2_hookup' ) )
        add_action( "admin_print_styles-{$this->options_page}", array( 'CMB2_hookup', 'enqueue_cmb_css' ) );
    }

    public function admin_page_display() {
      ?>
      <?php if ( $this->page['cmb2_id'] ) : ?>
        <div class="wrap cmb2-options-page <?php echo $this->page['id']; ?>">
          <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
          <?php cmb2_metabox_form( $this->page['cmb2_id'], $this->page['id'] ); ?>
        </div>
      <?php else : ?>
        <div class="wrap <?php echo $this->page['id']; ?>">
          <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
          <?php $this->app->do_action( 'options_page/' . $this->page['id'] ); ?>
        </div>
      <?php endif; ?>
      <?php
    }
  }
}
