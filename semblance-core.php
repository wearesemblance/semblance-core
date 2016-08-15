<?php
/*
Plugin Name: Semblance Core
Description: A core plugin for WordPress sites.
Plugin URI: http://wearesemblance.com/plugins/semblance-core
Author: Semblance <hi@wearesemblance.com>
Author URI: http://wearesemblance.com
Version: 1.0.0
License: GPL2
GitHub Plugin URI: https://github.com/semblance/semblance-core
GitHub Branch: master
*/

namespace WAS;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

if ( ! class_exists( __NAMESPACE__ . '\App' ) ) {
class App {
  public static $apps = array(); // Apps array

  public $id             = ''; // App id
  public $file           = ''; // App file
  public $type           = ''; // App type
  public $app_path       = ''; // App path
  public $app_url        = ''; // App url
  public $config         = array(); // App configs

  /**
   * Get Singleton Instance.
   *
   * Use this as an object instantiator as well as a method to retrieve an
   * object.
   *
   * @since 1.0.0
   *
   * @see \WAS\App::__construct()
   * @see \WAS\App::$apps
   *
   * @param string $id The string that identifies the app. Default false.
   * @param array|string $settings Optional. App settings. Default empty.
   * @param array|string $settings Optional. The __FILE__ from which this method was called. Include this if the current file's path is obscure. Default empty.
   * @return \WAS\App Returns the singleton instance. Otherwise returns false if requirements not met.
   */
  public static function instance( $id = false, $settings = array(), $file = false ) {
    // Return false if there is no file or we cannot trace a file
    if ( ! $file && ! $trace = debug_backtrace() ) return false;

    // Grab the file from whence this app came
    $file = ( ! $file ) ? $trace[0]['file'] : $file;

    // Singleton check
    if ( $id && array_key_exists( $id, self::$apps ) ) {
      return self::$apps[ $id ];
    } else if ( is_string( $id ) ) {
      return self::$apps[ $id ] = new self( $id, $settings, $file );
    }

    // Return false if ID doesn't exist...
    return false;
  }


  /**
   * Constructor
   *
   * Sets up the WAS Application, registers autoloader, sets up configurable app
   * settings, and initializes app hooks
   *
   * @since 1.0.0
   * @access private
   * @see \WAS\App::setup_config()
   *
   * @param string $id The string that identifies the app. Default false.
   * @param array|string $settings Optional. App settings. Default empty.
   * @param array|string $settings Optional. The __FILE__ from which this method was called. Include this if the current file's path is obscure. Default empty.
   * @return \WAS\App Returns the singleton instance. Otherwise returns false if requirements not met.
   */
  private function __construct( $id = false, $settings = array(), $file = false ) {
    // Composer autoload
    $autoloader = dirname( $file ) . '/vendor/autoload.php';
    if ( file_exists( $autoloader ) )
      require_once( $autoloader );

    // Class autoload
    if ( function_exists( "__autoload" ) )
      spl_autoload_register( "__autoload" );
    spl_autoload_register( array( $this, 'autoload' ) );

    // Set the id
    if ( ! $this->id )
      $this->id = $id;

    // Setup config vars
    $this->setup_config( $settings, $file );

    // Register apps on was/core/init
    $this->core_action( 'init', array( $this, 'init' ), $this->_get( 'config/app/priority' ) );

    // Init Core
    add_action( 'after_setup_theme', array( $this, 'init_core' ), 5 );
  }

  
  /**
   * Autoloader
   *
   * Autoloads classes based on a naming convention. Prefix your file names with class- abstract- interface- respectively.
   *
   * @since 1.0.0
   *
   * @see \WAS\App::__construct()
   *
   * @param string $class The class name as a string
   * @return void
   */
  public function autoload( $class ) {

    // Check for namespaces and grab the lowercased classname
    if ( strpos( $class, '\\' ) !== false )
      $class = strtolower( substr( $class, strrpos( $class, '\\' ) + 1 ) );
    else 
      $class = strtolower( $class );

    // Calc the file name
    $file = str_replace( '_', '-', strtolower( $class ) ) . '.php';
    $path = '';
    $prefix = '';
    
    // Abstracts
    if ( strpos( $class, 'abstract_' ) === 0 ) {
      $path = $this->dir( 'abstracts' );
    }
    
    // Interfaces
    if ( strpos( $class, 'interface_' ) === 0 ) {
      $path = $this->dir( 'interfaces' );
    }

    // Regular classes
    if ( empty( $path ) || ! $this->load_file( $path . $prefix . $file ) ) {
      $prefix = 'class-';
      $this->load_file( $this->dir( 'classes' ) . $prefix . $file );
    }
  }
  

  /**
   * Load a readable file
   *
   * A wrapper for include_once that checks if that file is readable. 
   *
   * @since 1.0.0
   * @access private
   *
   * @param string $path The file path to load.
   * @return void
   */
  private function load_file( $path ) {
    if ( $path && is_readable( $path ) ) {
      include_once( $path );
      return true;
    }
    return false;
  }

  
  /**
   * Getter
   *
   * Use to get object properties or app options from the wp_options table.
   *
   * @since 1.0.0
   *
   * @param string $key The property or option key to get. Nested array values
   *   can be traversed by separating keys via a "/""
   *   example: $this->_get("array/key/nested_key")
   * @return mixed The value retrieved. Returns null if not found.
   */
  public function _get( $key = '' ) {
    // Keys can be delimited by /... grab the array of keys
    $keys = explode( '/', $key );

    // Set a value to use in calculations
    $val = ( empty( $keys ) ) ? null : $this;
    foreach ( $keys as $i => $key ) {
      // If first key is "options"... look in the database
      if ( $i == 0 && $key == 'options' ) {

        // Calculate the second key in the loop...
        $option_key = sprintf( '%s_%s', $this->id, $keys[ $i + 1 ] );

        // Get the option
        $val = (array) get_option( $option_key, null );

        // Set the value to use in calculations
        $val = ( is_array( $val ) ) ? reset( $val ) : $val;
        continue;
      }

      // If we used options... skip the second key in the loop (since we already grabbed it)
      if ( $i == 1 && $keys[ $i - 1 ] == 'options' ) {
        continue;
      }

      // Calculate the value
      if ( is_object( $val ) && property_exists( $val, $key ) )
        $val = $val->$key;
      else if ( is_array( $val ) && array_key_exists( $key, $val ) )
        $val = $val[ $key ];
      else
        return null;
    }
    return $val;
  }

  /**
   * Load YAML Config
   *
   * Loads and parses YAML configuration files into the object's `config` property.
   *  The config array will store the YAML configuration into a key based on the
   *  $key parameter.
   *
   * @since 1.0.0
   * @see \WAS\App::$config
   * @see \WAS\App::setup_config()
   *
   * @param string $key The ID of the configuration to retrieve. Corresponds with
   *  The configuration's filename.
   * @return array The YAML config as an array.
   */
  public function load_config( $key = false ) {
    // Grab the config directory
    $file = $this->dir( 'config' ) . $key . '.yml';

    // Check if we need to load the file...
    if ( ! array_key_exists( $key, $this->config ) ) {

      // Load the file and set it in $this->config
      if ( file_exists( $file ) && function_exists( 'spyc_load_file' ) ) {
        return $this->config[ $key ] = spyc_load_file( $file );
      }

    // Return the config if it has already been loaded
    } else if ( array_key_exists( $key, $this->config ) ) {
      return $this->config[ $key ];
    }

    // Failsafe...
    return array();
  }

  /**
   * Initialize App Configuration
   *
   * Either loads all of the YAML config files inside of an app's `config`
   * directory or loads an optionally passed array of settings.
   *
   * Settings are parsed with app defaults corresponding to config IDs or
   * namespaces.
   *
   * @since 1.0.0
   * @access private
   * @see \WAS\App::$config
   * @see \WAS\App::load_config()
   * @see \WAS\App::get_default_config()
   *
   * @param array $settings Optional. An array of settings with the first level
   *   being config ID/namespaces.
   * @param string $file Optional. The file path to the app. If none is pased,
   *   will try to guess the location of the app file.
   * @return array The app's config property.
   */
  private function setup_config( $settings = array(), $file = false ) {
    // Set $this->file if we have not already
    if ( ! $this->file )
      $this->file = $file;

    // Calculate the type of app
    if ( strpos( $file, wp_normalize_path( WPMU_PLUGIN_DIR ) ) === 0 ) {
      $this->type = 'mu-plugin';
    } else if ( strpos( $file, wp_normalize_path( WP_PLUGIN_DIR ) ) === 0 ) {
      $this->type = 'plugin';
    } else if ( get_stylesheet_directory() === dirname( $file) ) {
      $this->type = 'theme';
    }

    // Grab the config directory
    $config_dir = $this->dir( 'config' );

    // If settings were NOT passed as an array, load a yml file
    if ( empty( $settings ) ) {
      $settings = array();

      // Grab config files (and ignore ../ and ./)
      $confs = array();
      if ( file_exists( $config_dir ) ) {
        $confs = array_diff( scandir( $config_dir ), array( '..', '.' ) );
      }

      // each config files
      if ( $confs ) {
        foreach ( $confs as $conf ) {
          $file_extension = pathinfo( $conf, PATHINFO_EXTENSION );

          // Skip non-PHP files
          if ( $file_extension != 'yml' && $file_extension != 'yaml'  )
            continue;

          // Skip defaults
          $id = pathinfo( $conf, PATHINFO_FILENAME );
          if ( strrpos( $id, '-defaults' ) + strlen( '-defaults' ) === strlen( $id ) )
            continue;

          // Load and set the config
          $this->load_config( $id );

          // Grab defaults and merge them with settings
          if ( $defaults = $this->get_default_config( $id ) ) {
            if ( ! empty( $defaults ) )
              $this->config[ $id ] = wp_parse_args( $this->config[ $id ], $defaults );
          }
        }
      } else {
        if ( ! isset( $settings['app'] ) ) {
          $settings['app'] = array();
        }
      }

    }

    // If we have passed the settings as an array
    if ( ! empty( $settings ) ) {

      // Foreach setting namespace
      foreach ( $settings as $id => $arr ) {
        // Grab defaults and merge them with settings
        if ( $defaults = $this->get_default_config( $id ) ) {
          if ( ! empty( $defaults ) )
            $this->config[ $id ] = wp_parse_args( $arr, $defaults );
        } else {
          $this->config[ $id ] = $arr;
        }
      }
    }

    // Return configuration array
    return $this->config;
  }


  /**
   * Get default configuration
   *
   * Loads a configuration YAML file of mergable default values.
   *
   * @since 1.0.0
   * @see \WAS\App::setup_config()
   * @see \WAS\App::load_config()
   *
   * @param array $id Optional. The ID or namespace of the configuration.
   *   will try to guess the location of the app file.
   * @return array The default configuration of the passed namespace.
   */
  public function get_default_config( $id = 'app' ) {

    // If core has been loaded...
    if ( $core = $this->get_core() ) {

      // Grab core location and app location of default config
      $core_file = $core->dir( 'config' ) . $id . '-defaults.yml';
      $app_file = $this->dir( 'config' ) . $id . '-defaults.yml';

      // Load filtered defaults first
      if ( $filtered_defaults = $this->apply_filters( 'pre_get_config_defaults_' . $id, false ) ) {
        $defaults = $filtered_defaults;

      // Else load app defaults
      } else if ( file_exists( $app_file ) ) {
        $defaults = $this->load_config( $id . '-defaults' );

      // Else load core defaults
      } else if ( file_exists( $core_file ) ) {
        $defaults = $core->load_config( $id . '-defaults' );
      }

      return $this->apply_filters( 'get_config_defaults_' . $id, $defaults );
    }

    // Failsafe...
    return array();
  }


  /**
   * Is this the app the core app?
   *
   * @since 1.0.0
   * @see \WAS\App::is_plugin()
   * @see \WAS\App::is_mu_plugin()
   *
   * @return bool True if the app in question is the 'core' app, otherwise false.
   */
  public function is_core() {
    return ( ( $this->is_plugin() || $this->is_mu_plugin() ) && $this->id == 'was_core' );
  }


  /**
   * Is this the app a plugin?
   *
   * @since 1.0.0
   *
   * @return bool True if the app in question is a WordPress plugin.
   */
  public function is_plugin() {
    return ( $this->type == 'plugin' );
  }


  /**
   * Is this the app a must use plugin?
   *
   * @since 1.0.0
   *
   * @return bool True if the app in question is a WordPress must use plugin.
   */
  public function is_mu_plugin() {
    return ( $this->type == 'mu-plugin' );
  }


  /**
   * Is this the app a theme?
   *
   * @since 1.0.0
   *
   * @return bool True if the app in question is a WordPress theme.
   */
  public function is_theme() {
    return ( $this->type == 'theme' );
  }


  /**
   * Get an app
   *
   * Retrieves the singleton instance of an app based on its ID
   *
   * @since 1.0.0
   * @see \WAS\App::$apps
   * @see \WAS\App::instance()
   *
   * @param string $id The ID of the app to retrieve
   * @return \WAS\App The app singleton instance
   */
  public function get_app( $id = false ) {
    return ( $id ) ? self::instance( $id ) : false;
  }


  /**
   * Get all apps
   *
   * Retrieves the singleton instances of all registered apps
   *
   * @since 1.0.0
   * @see \WAS\App::$apps
   * @see \WAS\App::instance()
   *
   * @return array The singleton instances of all registered apps
   */
  public function get_apps() {
    return array_values( self::$apps );
  }


  /**
   * Get the core app
   *
   * Retrieves the singleton instance of the core app
   *
   * @since 1.0.0
   * @see \WAS\App::get_app()
   *
   * @return array The singleton instances of the core app
   */
  public function get_core() {
    return ( $this->is_core() ) ? $this : $this->get_app( 'was_core' );
  }


  /**
   * Get the theme app
   *
   * Retrieves the singleton instance of the current theme if it is a
   * registered app
   *
   * @since 1.0.0
   * @see \WAS\App::get_app()
   *
   * @return array The singleton instances of the theme app if it exists, otherwise false.
   */
  public function get_theme_app() {
    foreach ( $this->get_apps() as $app ) {
      if ( $app->is_theme() )
        return $app;
    }
    return false;
  }

  // Get path or url based on identifier
  public function app_location( $sector = '', $base = false ) {
    if ( ! $base ) {
      $path = $base;
      $base = $this->dir();
    }

    switch ( $sector ) {
      case 'php':        $path = $base . 'php/'; break;
      case 'shortcodes': $path = $base . 'php/shortcodes/'; break;
      case 'classes':    $path = $base . 'php/classes/'; break;
      case 'abstracts':  $path = $base . 'php/classes/abstracts/'; break;
      case 'interfaces': $path = $base . 'php/classes/interfaces/'; break;
      case 'updates':    $path = $base . 'php/updates/'; break;
      case 'js':         $path = $base . 'dist/scripts/'; break;
      case 'css':        $path = $base . 'dist/styles/'; break;
      case 'images':     $path = $base . 'dist/assets/'; break;
      case 'vendor':     $path = $base . 'vendor/'; break;
      case 'plugins':    $path = $base . 'plugins/'; break;
      case 'config':     $path = $base . 'config/'; break;

      default:
        $path = $base;
      break;
    }

    return $path;
  }

  // Get full app path
  public function dir( $sector = '' ) {
    // If the app path has not yet been set
    if ( ! $this->app_path ) {

      // If the app is a plugin or mu-plugin
      if ( $this->is_plugin() || $this->is_mu_plugin() ) {

        // Set the app path to the dirname of the app file
        $this->app_path = plugin_dir_path( $this->file );

      // If the app is a theme
      } else if ( $this->is_theme() ) {

        // Set the app path to the stylesheet directory
        $this->app_path = trailingslashit( get_stylesheet_directory() );
      }
    }

    // Return the location with the app path as the base
    return $this->app_location( $sector, $this->app_path );
  }

  // Get full app url
  public function url( $sector = '' ) {
    // If the app url has not yet been set

    if ( ! $this->app_url ) {

      // If the app is a plugin or mu-plugin
      if ( $this->is_plugin() || $this->is_mu_plugin() ) {

        // Set the app url to the URI to the app file
        $this->app_url = plugin_dir_url( $this->file );

      // If the app is a theme
      } else if ( $this->is_theme() ) {

        // Set the app url to the URI to the stylesheet directory
        $this->app_url = trailingslashit( get_stylesheet_directory_uri() );
      }
    }

    // Return the location with the app url as the base
    return $this->app_location( $sector, $this->app_url );
  }

  // Insert an item into an array based on position
  private function array_insert( &$array, $position, $insert ) {
    if ( is_int( $position ) ) {
      array_splice( $array, $position, 0, $insert );
    } else {
      $pos   = array_search( $position, array_keys( $array ) );
      $array = array_merge(
        array_slice( $array, 0, $pos ),
        $insert,
        array_slice( $array, $pos )
      );
    }
  }

  // Apply filters based on app id
  public function apply_filters( $tag, $arg = ''  ) {
    $key = ( $this->is_core() ) ? 'was_core' : $this->id;
    $tag = sprintf( '%s/%s', $key, $tag );
    $args = func_get_args();
    $args[0] = $tag;
    array_push( $args, $this );
    return call_user_func_array( 'apply_filters', $args );
  }

  // Do action based on app id
  public function do_action( $tag, $arg = ''  ) {
    $key = ( $this->is_core() ) ? 'was_core' : $this->id;
    $tag = sprintf( '%s/%s', $key, $tag );
    $args = func_get_args();
    $args[0] = $tag;
    array_push( $args, $this );
    return call_user_func_array( 'do_action', $args );
  }

  // Queue a filter based on app id
  public function add_filter( $tag, $function, $priority = 10, $args = 1 ) {
    $key = ( $this->is_core() ) ? 'was_core' : $this->id;
    $tag = sprintf( '%s/%s', $key, $tag );
    return add_filter( $tag, $function, $priority, $args );
  }

  // Queue an action based on app id
  public function add_action( $tag, $function, $priority = 10, $args = 1 ) {
    $key = ( $this->is_core() ) ? 'was_core' : $this->id;
    $tag = sprintf( '%s/%s', $key, $tag );
    return add_action( $tag, $function, $priority, $args );
  }

  // Queue an action for core
  public function core_action( $tag, $function, $priority = 10, $args = 1 ) {
    $tag = sprintf( 'was_core/%s', $tag );
    return add_action( $tag, $function, $priority, $args );
  }

  // Core init
  public function init_core() {
    if ( ! $this->is_core() ) return; // Skip non-core
    $this->includes(); // Core includes
    $this->do_action( 'register_apps' ); // Register apps
    $this->do_action( 'before_init' ); // Before core
    $this->do_action( 'init' ); // Init core (and apps)

    $shortcodes = $this->dir( 'shortcodes' );

    $this->core_action( 'after_setup', array( $this, 'init_loop_layouts' ), 99 );
    $this->do_action( 'after_setup' ); // After app setup
    $loops = Loop_Layouts::instance();
  }

  // App init
  public function init() {
    // if ( ! $this->can_init() ) return;

    // Common actions and filters
    $this->actions_and_filters();
    
    // Layouts
    if ( class_exists( '\WAS\Abstract_Layout_Core' ) ) {
      $this->do_action( 'layouts/before_load' );
      $this->layouts();
      $this->do_action( 'layouts/after_load' );
    }

    // Custom Options Pages
    $options = $this->_get( 'config/options-pages' );
    if ( $options ) foreach ( $options as $page ) { 
      $page['_app'] = $this;
      Options_Page::instance( $page );
    }

    // Custom image sizes
    $this->add_image_sizes();

    // Init hook for non-core apps
    if ( ! $this->is_core() ) $this->do_action( 'init' );

    $acf_builder = ACF_Builder::instance( $this );
    $cmb2_builder = CMB2_Builder::instance( $this );

    if ( $this->is_theme() ) {
      $theme_customizations = Theme_Customizations::instance( $this );
    }

    // Setup is complete
    $this->do_action( 'setup_complete' );

  }

  public function init_loop_layouts() {
    $loops = Loop_Layouts::instance();
  }

  // Should this app initialize?
  public function can_init( $id = '' ) {
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

    $id = ( $id ) ? $id : $this->id;
    $app = $this->get_app( $id );
    $required = $app->_get( 'config/plugins/required' );
    
    $ready = true;
    if ( $required ) foreach ( $required as $plugin ) {
      if ( ! is_plugin_active( $plugin['slug'] ) )
        $ready = false;
    }

    return $ready;
  }

  // Actions and filters
  public function actions_and_filters() {
    if ( is_admin() )
      add_action( 'admin_init', array( $this, 'register_assets' ) );
    else 
      add_action( 'wp', array( $this, 'register_assets' ) );

    add_action( 'admin_init', array( $this, 'updates' ), 5 );

    // Fancy ajax callback
    add_action( 'wp_ajax_was_core', array( $this, 'core_ajax_callback' ) );
    add_action( 'wp_ajax_nopriv_was_core', array( $this, 'core_ajax_callback' ) );

    add_action( 'init', array( $this, 'register_post_types' ) );
    add_action( 'init', array( $this, 'register_taxonomies' ) );

    add_action( 'widgets_init', array( $this, 'register_sidebars' ) );
    $this->add_action( 'register_sidebar', '\register_sidebar', 10, 1 );

    add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
    add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
    add_action( 'admin_init', array( $this, 'editor_css' ) );
    add_filter( 'mce_external_plugins', array( $this, 'editor_js' ) );

    add_action( 'tgmpa_register', array( $this, 'required_plugins' ) );
  }

  // Install & Updates
  public function updates() {
    // Grab the version in the database
    $db_version = $this->_get( 'options/version' );

    // Grab path to the update scripts
    $updates_dir = $this->dir( 'updates' );

    // Don't do an update if we are up to date
    if ( $db_version == $this->_get( 'config/app/version' ) ) return;

    // Check for initial install
    if ( ! defined( 'IFRAME_REQUEST' ) && ( $db_version === false ) ) {
      $this->install();

    // If the database version does not match the real version
    } elseif ( $db_version != $this->_get( 'config/app/version' ) ) {

      // If we have updates
      if ( file_exists( $updates_dir ) ) {

        // Grab update scripts (and ignore ../ and ./)
        $updates = array_diff( scandir( $updates_dir ), array( '..', '.' ) );

        // Update in sequential order
        foreach ( $updates as $update_file ) {

          // Skip non-PHP files
          if ( pathinfo( $update_file, PATHINFO_EXTENSION ) != 'php' )
            continue;

          // Skip older versions
          if ( version_compare( basename( $update_file, '.php' ), $db_version, '<=' ) )
            continue;

          // What version is this file?
          $old_version = $db_version;
          $new_version = $db_version = basename( $update_file, '.php' );

          // Before Update
          $this->do_action( 'before_update', $new_version, $old_version );
          $this->do_action( 'before_update_' . $new_version, $new_version, $old_version );

          // Update
          include_once( trailingslashit( $updates_dir ) . $update_file );

          // After Update
          $this->do_action( 'after_update_' . $new_version, $new_version, $old_version );
          $this->do_action( 'after_update', $new_version, $old_version );

          // Log the update for later redirection
          $updated = true;

          // Stop once we reach the current version
          // ( Just in case update scripts are being developed ahead of time )
          if ( version_compare( basename( $update_file, '.php' ), $this->_get( 'config/app/version' ), '=' ) )
            break;
        }
      }
    }

    // Update the version in the database
    update_option( sprintf( '%s_%s', $this->id, 'version' ), $this->_get( 'config/app/version' ) );
    // // Redirect to an "About" Page
    // wp_safe_redirect( admin_url( 'index.php?page=' . $this->plugin_slug . '-about&' . $this->plugin_slug . '-updated=true' ) );
    // exit;
  }

  // Install
  public function install() {
    // Don't do anything if we have already installed
    if ( $this->_get( 'options/version' ) ) return;

    // Grab the install script
    $file = $this->dir( 'updates' ) . 'install.php';

    // Before Install
    $this->do_action( 'before_install' );

    // Include the first install
    if ( file_exists( $file ) )
      include_once( $file );

    // After Install
    $this->do_action( 'after_install' );
  }

  // A fancy ajax callback
  public function core_ajax_callback() {    
    // Grab the data
    $data = ( isset( $_REQUEST['ajaxArgs'] ) ) ? $_REQUEST['ajaxArgs'] : false;

    // The passed ajaxArgs looks like array(
    //     'object' => Any encrypted and serialized object,
    //     'callback' => Any kind of callback to run, can be a function or a method,
    //     'args' => array() An array of parameters to pass to the callback
    // );
    
    // Die if there is no data
    if ( ! $data ) wp_die();

    if ( is_array( $data ) )
      $data = json_decode( json_encode( $data ) );

    // Convert data array to object
    else
      $data = decode_object( $data );

    // If a callback is passed...
    if ( isset( $data->callback ) ) {
      header( "Content-type: application/json" );

      // Set $args to false if there are no parameters
      $args = ( isset( $data->args ) ) ? $data->args : false;

      try {
        // If no $args parameters, just call_user_func
        if ( ! $args )
          $output = call_user_func( $data->callback );
        // Else call_user_func_array with the $args
        else
          $output = call_user_func_array( $data->callback, $args );  

        echo json_encode( array(
          'response' => $output
        ) );
      } catch ( Exception $e ) {

      }
    }

    // Always die(); after an AJAX callback
    wp_die();
  }

  // Init Genesis layouts
  public function layouts() {
    $loop_layouts = $this->_get( 'config/loop-layouts' );
    $loop_layout_options = ( $this->is_core() ) ? get_option( 'was_loop_layouts' ) : false;

    $this->do_action( 'layouts/load' );

    if ( (array) $loop_layouts || $loop_layout_options )
      add_filter( 'was_core/loop_layouts', array( $this, 'loop_layouts' ) );

  }

  // Loop layouts
  public function loop_layouts( $loop_layouts ) {
    if ( (array) $this->_get( 'config/loop-layouts' ) )
      $loop_layouts = array_merge( $loop_layouts, (array) $this->_get( 'config/loop-layouts' ) );

    if ( $custom_layouts = get_option( 'was_loop_layouts' ) ) {
      $layouts = array();
      foreach ( $custom_layouts['_group'] as $key => $layout ) {
        if ( isset( $layout['query_args'] ) && ! empty( $layout['query_args'] ) ) {
          $args = $layout['query_args'];
          $args = explode( PHP_EOL, trim( $args ) );

          $vars = array();
          foreach ( $args as $key => $arg ) {
            $arg = array_map( 'trim', explode( ':', $arg ) );
            if ( count( $arg ) == 2 )
              $vars[ $arg[0] ] = $arg[1]; 
          }

          $layout['query_args'] = $vars;
        } else { $layout['query_args'] = array(); }

        $layouts[] = array(
          'condition' => $layout['condition'],
          'settings' => $layout,
        );
      }
      $loop_layouts = array_merge( $loop_layouts, (array) $layouts );
    }

    return $loop_layouts;
  }

  // Includes
  public function includes() {
    $this->do_action( 'before_includes' );
    $dir = $this->dir();

    // Helpers
    if ( $this->is_core() ) {
      include_once( $this->dir( 'php' ) . 'helpers.php' );

      // Custom Meta Boxes 2
      $cmb2_dir = $this->dir( 'vendor' );
      if ( file_exists(  $cmb2_dir . 'cmb2/init.php' )  ) {
        require_once  $cmb2_dir . 'cmb2/init.php';
      } else if ( file_exists(  $cmb2_dir . 'CMB2/init.php' ) ) {
        require_once  $cmb2_dir . 'CMB2/init.php';
      }
    }

    $this->do_action( 'includes', $dir );
  }

  // Custom image sizes
  public function add_image_sizes() {
    $sizes = $this->_get( 'config/app/image_sizes' );

    if ( ! empty( $sizes ) ) {
      foreach ( (array) $sizes as $key => $size ) {
        $w = isset( $size[0] ) ? $size[0] : 0;
        $h = isset( $size[1] ) ? $size[1] : $w;
        $c = isset( $size[2] ) ? $size[2] : false;
        add_image_size( sanitize_title( $key ), $w, $h, $c );
      }

      add_filter( 'image_size_names_choose', array( $this, 'add_image_sizes_to_editor' ) );
    }
  }

  // Add image sizes to post editor
  public function add_image_sizes_to_editor( $sizes ) {
    $custom = $this->_get( 'config/app/image_sizes' );
    if ( empty( $custom ) )
      return $sizes;

    foreach ( $custom as $key => $data ) {
      $id = sanitize_title( $key );

      if ( ! isset( $sizes[ $id ] ) )
        $sizes[ $id ] = $key;
    }

    return $sizes;
  }

  // Register post types
  public function register_post_types() {
    $cpts = $this->_get( 'config/post-types' );

    if ( (array) $cpts ) foreach ( $cpts as $slug => $args ) {
      $args = $this->apply_filters( $slug . '/post_type_args', $args );
      if ( ! post_type_exists( $slug ) )
        register_post_type( $slug, $args );
    }

    $file = $this->dir( 'php' ) . 'post-types.php';
    if ( file_exists( $file ) )
      include_once( $file );
  }

  // Register taxonomies
  public function register_taxonomies() {
    $cpts = $this->_get( 'config/taxonomies' );

    if ( (array) $cpts ) foreach ( $cpts as $slug => $args ) {
      $args = $this->apply_filters( $slug . '/taxonomy_args', $args );

      if ( array_key_exists( 'post_types', $args ) ) {
        $post_types = $args['post_types'];
        unset( $args['post_types'] );
      } else {
        $post_types = array();
      }

      register_taxonomy( $slug, (array) $post_types, $args );
    }

    $file = $this->dir( 'php' ) . 'taxonomies.php';
    if ( file_exists( $file ) )
      include_once( $file );
  }

  // Register Widget Areas
  public function register_sidebars() {
    $sidebars = $this->_get( 'config/app/sidebars' );

    if ( (array) $sidebars ) foreach ( $sidebars as $args ) {
      $args = $this->apply_filters( 'register_sidebar_args',  $args );
      $this->do_action( 'register_sidebar', $args );
    }
  }

  // TGM Plugin Activation
  public function required_plugins() {
    $plugins = array();

    // Required Plugins
    $required = $this->_get( 'config/plugins/required' );
    foreach ( (array) $required as $prefs ) {

      // Allow higher priority apps to cancel plugin
      if ( $prefs['cancel'] ) continue;
      
      $plugins[] = array_merge( $prefs, array(
        'required' => true,
        'force_activation' => true,
      ) );
    }

    // Optional plugins
    $optional = $this->_get( 'config/plugins/optional' );
    foreach ( (array) $optional as $prefs ) {

      // Allow higher priority apps to cancel plugin
      if ( $prefs['cancel'] ) continue;
      
      $plugins[] = array_merge( $prefs, array(
        'required' => false,
        'force_activation' => false,
      ) );
    }

    $config = array(
      'id'           => $this->id,               // Unique ID for hashing notices for multiple instances of TGMPA.
      'default_path' => $this->dir( 'plugins' ), // Default absolute path to bundled plugins.
      'menu'         => 'tgmpa-install-plugins', // Menu slug.
      'parent_slug'  => 'plugins.php',           // Parent menu slug.
      'capability'   => 'edit_theme_options',    // Capability needed to view plugin install page, should be a capability associated with the parent menu used.
      'has_notices'  => true,                    // Show admin notices or not.
      'dismissable'  => true,                    // If false, a user cannot dismiss the nag message.
      'dismiss_msg'  => '',                      // If 'dismissable' is false, this message will be output at top of nag.
      'is_automatic' => true ,                   // Automatically activate plugins after installation or not.
      'message'      => '',                      // Message to output right before the plugins table.
    );
    tgmpa( $plugins, $config );
  }

  // Register assets
  public function register_assets() {
    $js = (array) $this->_get( 'config/app/js' );
    if ( $js ) foreach ( $js as $asset ) {
      $asset = $this->parse_asset( $asset );
      if ( $asset['is_remote'] == false ) {
        $asset['src'] = trailingslashit( $this->url( 'js' ) ) . $asset['src'];
      }
      if ( $this->apply_filters( 'register_js/' . $asset['handle'], true ) ) {
        wp_register_script( $asset['handle'], $asset['src'], $asset['deps'], $asset['ver'], $asset['in_footer'] );
      }
    }

    $css = (array) $this->_get( 'config/app/css' );
    if ( $css ) foreach ( $css as $asset ) {
      $asset = $this->parse_asset( $asset );
      if ( $asset['is_remote'] == false ) {
        $asset['src'] = trailingslashit( $this->url( 'css' ) ) . $asset['src'];
      }
      if ( $this->apply_filters( 'register_css/' . $asset['handle'], true ) ) {
        wp_register_style( $asset['handle'], $asset['src'], $asset['deps'], $asset['ver'], $asset['media'] );
      }
    }

    $this->do_action( 'register_scripts' );
  }

  // Enqueue assets
  public function enqueue_assets( $location ) {
    $location_map = array(
      'admin' => 'is_admin is true',
      'frontend' => 'is_admin is false',
      'global' => 'variable true is true',
    );

    $this->do_action( 'before_enqueue_assets/' . $location ); 

    $validator = \WAS\Condition_Validator::instance();

    $js = (array) $this->_get( 'config/app/js' );
    if ( $js ) foreach ( $js as $asset ) {
      $asset = $this->parse_asset( $asset );
      foreach ( (array) $asset['location'] as $asset_location ) {
        if ( $location != 'editor' && $asset_location != 'editor' ) {
          if ( array_key_exists( (string) $asset_location, $location_map ) ) {
            $asset_location = str_replace( array_keys( $location_map ), array_values( $location_map ), $asset_location );
          }

          if ( $validator->validate_condition( $asset_location ) ) {
            if ( $this->apply_filters( 'enqueue_js/' . $asset['handle'], true ) ) {
              wp_enqueue_script( $asset['handle'] );
            }
          }
        }
      }
    }

    $css = (array) $this->_get( 'config/app/css' );
    if ( $css ) foreach ( $css as $asset ) {
      $asset = $this->parse_asset( $asset );
      foreach ( (array) $asset['location'] as $asset_location ) {
        if ( $location != 'editor' ) {
          if ( array_key_exists( (string) $asset_location, $location_map ) ) {
            $asset_location = str_replace( array_keys( $location_map ), array_values( $location_map ), $asset_location );
          }

          if ( $validator->validate_condition( $asset_location ) ) {
            if ( $this->apply_filters( 'enqueue_css/' . $asset['handle'], true ) ) {
              wp_enqueue_style( $asset['handle'] );
            }
          }
        } else if ( $asset_location == 'editor' ) {
          if ( $asset['is_remote'] == false ){
            $asset['src'] = trailingslashit( $this->url( 'css' ) ) . $asset['src'];
          }

          if ( $this->apply_filters( 'enqueue_css/' . $asset['handle'], true ) ) {
            add_editor_style( $asset['src'] );
          }
        }
      }
    }
  }

  // Parse asset
  public function parse_asset( $asset = array() ) {
    $asset_defaults = array(
      'handle' => '',
      'src' => '',
      'deps' => array(),
      'ver' => '',
      'in_footer' => true,
      'media' => true,
      'location' => '',
      'is_remote' => false,
    );

    $asset = wp_parse_args( $asset, $asset_defaults );
  }

  // Admin scripts 
  public function admin_scripts() {
    // Register Assets
    $this->enqueue_assets( 'admin' );
  }

  // Frontend scripts 
  public function frontend_scripts() {
    // Register Assets
    $this->enqueue_assets( 'frontend' );
  }

  // TinyMCE styles 
  public function editor_css() {
    // Register Assets
    $this->enqueue_assets( 'editor' );
  }

  // TinyMCE plugins 
  public function editor_js( $plugin_array ) {
    $js = (array) $this->_get( 'config/app/js' );
    if ( $js ) foreach ( $js as $asset ) {
      $asset = $this->parse_asset( $asset );
      if ( $asset['is_remote'] == false ) {
        $asset['src'] = trailingslashit( $this->url( 'js' ) ) . $asset['src'];
      }

      foreach ( (array) $asset['location'] as $asset_location ) {
        if ( in_array( $asset_location, array( 'editor' ) ) ) {
          if ( $this->apply_filters( 'enqueue_js/' . $asset['handle'], true ) ) {
            $plugin_array[ $asset['handle'] ] = $asset['src'];
          }
        }
      }
    }

    return $plugin_array;
  }
}
}

$GLOBALS['was_core'] = App::instance( 'was_core' );
