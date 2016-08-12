<?php

namespace WAS;

class Taxonomy_Query {
  public $current_term = -1;
  public $term_count = 0;
  public $terms;
  public $term;
  public $in_the_loop = false;

  // public static $instance = null;
  // public static function instance() {
  //   if ( is_null( self::$instance ) )
  //     return self::$instance = new self();
  //   else
  //     return self::$instance;
  // }

  public function __construct( $taxonomies = '', $args = array() ) {
    if ( ! empty( $taxonomies ) ) {
      $this->get_terms( $taxonomies, $args );
    }
  }

  public function get_terms( $taxonomies = '', $args = array() ) {
    global $term;

    $this->terms = get_terms( $taxonomies, $args );
    $this->term_count = $this->found_terms = count( $this->terms );
    $this->terms = ( is_array( $this->terms ) ) ? array_values( $this->terms ) : $this->terms;

    if ( isset( $args['number'] ) ) {
      $args_clone = $args;
      unset( $args_clone['number'] );
      $args_clone['fields'] = 'count';
      $this->found_terms = get_terms( $taxonomies, $args_clone );
    }

    if ( $this->terms ) {
      $this->term = reset($this->terms);
    } else {
      $this->term_count = 0;
      $this->terms = array();
    }

    return $this->terms;
  }

  public function have_terms() {
    if ( $this->current_term + 1 < $this->term_count ) {
      return true;
    } elseif ( $this->current_term + 1 == $this->term_count && $this->term_count > 0 ) {
      do_action_ref_array( 'tax_loop_end', array( &$this ) );
      $this->rewind_terms();
    }

    $this->in_the_loop = false;
    return false;
  }

  public function the_term() {
    global $term;
    $this->in_the_loop = true;

    if ( $this->current_term == -1 )
      do_action_ref_array( 'tax_loop_start', array( &$this ) );

    $term = $this->next_term();
    $this->setup_termdata( $term );
  }

  public function next_term() {
    $this->current_term++;

    $this->term = $this->terms[ $this->current_term ];
    return $this->term;
  }

  public function setup_termdata( $term ) {
    do_action_ref_array( 'the_term', array( &$term, &$this ) );
    return true;
  }

  public function reset_termdata() {
    if ( ! empty( $this->term ) ) {
      $GLOBALS['term'] = $this->term;
      setup_termdata( $this->term );
    }
  }

  public function rewind_terms() {
    $this->current_term = -1;
    if ( $this->term_count > 0 ) {
      $this->term = $this->terms[0];
    }
  }
}
