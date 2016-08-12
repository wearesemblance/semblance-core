<?php

namespace WAS;

class CMB2_Builder {
  public $app = null;
  public static $loaded_custom = false;

  public static $instances = array();
  public static function instance( $app = false ) {
    if ( ! $app || ! property_exists( $app, 'id' ) )
      return;

    if ( ! array_key_exists( $app->id, self::$instances ) )
      return self::$instances[ $app->id ] = new self( $app );
    else
      return self::$instances[ $app->id ];
  }

  public function __construct( $app ) {
    $this->app = $app;
    add_action( 'cmb2_admin_init', array( $this, 'meta_boxes' ) );
    
    if ( ! self::$loaded_custom ) {
      add_action( 'cmb2_render_radio_other', array( $this, 'radio_other' ), 10, 5 );
      add_filter( 'cmb2_list_input_other_attributes', array( $this, 'other_radio_attributes' ), 10, 4 );
      add_filter( 'cmb2_list_input_other_text_attributes', array( $this, 'other_text_attributes' ), 10, 4 );
      self::$loaded_custom = true;
    }
  }

  public function meta_boxes() {
    $cmb = $this->app->_get( 'config/cmb2' );

    if ( (array) $cmb ) foreach ( $cmb as $box ) {
      if ( ! isset( $box['id'] ) )
        continue;

      $fields = ( isset( $box['fields'] ) ) ? (array) $box['fields'] : array();

      if ( isset( $box['fields'] ) )
        unset( $box['fields'] );

      $box = new_cmb2_box( $box );

      if ( $fields ) foreach ( $fields as $field ) {
        $subfields = ( isset( $field['fields'] ) ) ? (array) $field['fields'] : array();

          if ( isset( $field['fields'] ) )
            unset( $field['fields'] );
        
        $group_id = $box->add_field( $field );

        if ( $subfields ) foreach ( $subfields as $subfield ) {
          $this->add_repeater_field( $box, $group_id, $subfield );
        }
      }
    }
  }

  public function add_repeater_field( $box, $group_id, $field ) {
    $subfields = ( isset( $field['fields'] ) ) ? (array) $field['fields'] : array();

    if ( isset( $field['fields'] ) )
      unset( $field['fields'] );
    
    $new_id = $box->add_group_field( $group_id, $field );

    if ( $subfields ) foreach ( $subfields as $subfield ) {
      $this->add_repeater_field( $box, $new_id, $subfield );
    }
  }

  public function radio_other( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {
    $other = '';
    if ( $field->args && array_key_exists( 'show_option_other', (array) $field->args ) ) {

      $a = $field_type_object->parse_args( $args, 'list_input_other', array(
        'type'  => 'radio',
        'class' => 'cmb2-option',
        'name'  => $field_type_object->_name(),
        'id'    => $field_type_object->_id( 'other' ),
        'value' => '',
        'label' => $field->args['show_option_other'],
        'onclick' => sprintf( "document.getElementById('%s').focus()", $field_type_object->_id( 'othertext' ) ),
      ) );

      $b = $field_type_object->parse_args( $args, 'list_input_other_text', array(
        'type'  => 'text',
        'class' => 'cmb2-option',
        'name'  => '',
        'id'    => $field_type_object->_id( 'othertext' ),
        'value' => '',
        'label' => 'Other',
        'onclick' => sprintf( "document.getElementById('%s').checked = true", $field_type_object->_id( 'other' ) ),
        'onkeyup' => sprintf( "document.getElementById('%s').value = document.getElementById('%s').value", $field_type_object->_id( 'other' ), $field_type_object->_id( 'othertext' ) ),
      ) );

      $input = sprintf( '<input%s/>', $field_type_object->concat_attrs( $b, array( 'label' ) ) );

      $other = sprintf( "\t" . '<li><input%s/> <label for="%s">%s</label> %s</li>' . "\n", $field_type_object->concat_attrs( $a, array( 'label' ) ), $a['id'], $a['label'], $input );
    }

    $a = $field_type_object->parse_args( $field, 'radio', array(
      'class'   => 'cmb2-radio-list cmb2-list',
      'options' => $field_type_object->concat_items( array( 'label' => 'test', 'method' => 'list_input' ) ),
      'desc'    => $field_type_object->_desc( true ),
    ) );

    printf( '<ul class="%s">%s%s</ul>%s', $a['class'], $a['options'], $other, $a['desc'] );
  }

  public function other_text_attributes( $args, $defaults, $field, $field_type_object ) {
    $value = $field->escaped_value()
      ? $field->escaped_value()
      : $field->args( 'default' );

    if ( $value && ! array_key_exists( $value, $field->options() ) ) {
      $args['value'] = $value;
    }

    return $args;
  }

  public function other_radio_attributes( $args, $defaults, $field, $field_type_object ) {
    $value = $field->escaped_value()
      ? $field->escaped_value()
      : $field->args( 'default' );

    if ( $value && ! array_key_exists( $value, $field->options() ) ) {
      $args['checked'] = true;
      $args['value'] = $value;
    }

    return $args;
  }
}