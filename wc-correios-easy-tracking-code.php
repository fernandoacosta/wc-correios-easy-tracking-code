<?php
/*
 * Plugin Name: WC Correios Easy Tracking Code
 * Description: Adicione código de rastreio dos Correios sem precisar abrir o pedido no WooCommerce.
 * Plugin URI: http://fernandoacosta.net
 * Author: Fernando Acosta
 * Author URI: http://fernandoacosta.net
 * Version: 1.2.1
 * Requires at least: 5.0
 * Tested up to: 5.5.1
 * WC requires at least: 3.5.0
 * WC tested up to:      4.6.1
 * License: GPL2
*/
/*
  Copyright (C) 2016  Fernando Acosta  contato@fernandoacosta.net
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.
  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.
  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
/**
* WC Correios Tracking Code Column
*/
class WC_Correios_Tracking_Code_Column {
  /**
   * Instance of this class.
   *
   * @var object
   */
  protected static $instance = null;

  /**
   * Initialize the plugin public actions.
   */
  function __construct() {
    add_filter( 'woocommerce_admin_order_actions', array( $this, 'wc_correios_shipping_tracking' ), 10, 2 );
    add_action( 'woocommerce_admin_order_actions_end', array( $this, 'wc_correios_shipping_tracking_field' ), 10, 1 );
    add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts' ) );

    add_action( 'wp_ajax_wc_add_correios_tracking', array( $this, 'wc_add_correios_tracking' ) );
    add_action( 'wp_ajax_nopriv_wc_add_correios_tracking', array( $this, 'wc_add_correios_tracking' ) );
  }

  /**
   * Return an instance of this class.
   *
   * @return object A single instance of this class.
   */
  public static function get_instance() {
    // If the single instance hasn't been set, set it now.
    if ( null == self::$instance ) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  /**
   * Get main file.
   *
   * @return string
   */
  public static function get_main_file() {
    return __FILE__;
  }

  /**
   * Get plugin path.
   *
   * @return string
   */
  public static function get_plugin_path() {
    return plugin_dir_path( __FILE__ );
  }

  public function add_scripts() {
    wp_enqueue_style( 'wc-correios-easy-tracking-code-css', plugins_url( '/', $this->get_main_file() ) . '/assets/wc-correios-easy-tracking-code.css', array(), 1.2 );
    wp_enqueue_script( 'wc-correios-easy-tracking-code-js', plugins_url( '/', $this->get_main_file() ) . '/assets/wc-correios-easy-tracking-code.js', array(), 1.2, true );
  }

  public function wc_correios_shipping_tracking( $actions, $the_order ) {

    $tracking_code = $the_order->get_meta( '_correios_tracking_code' );

    $actions['wc-correios-tracking'] = array(
      'action' => 'wc-correios-tracking',
      'url' => wp_nonce_url( admin_url( 'post.php?post=' . $the_order->get_id() . '&action=edit' ), 'wc-correios-tracking' ),
      'name' => '' === $tracking_code ? 'Adicionar código de rastreio' : 'Código de rastreio já adicionado',
    );

    return $actions;
  }

  public function wc_correios_shipping_tracking_field( $the_order ) {

    $tracking_code = $the_order->get_meta( '_correios_tracking_code' );
    $tracking_code = is_array( $tracking_code ) ? array_shift( $tracking_code ) : $tracking_code;

    echo '<input type="text" class="no-link wc-correios-tracking-field" data-order-id="' . $the_order->get_id() . '" placeholder="Rastreio" value="' . $tracking_code .'" />';
  }

  public function wc_add_correios_tracking() {

    $order_id = $_POST['order_id'];
    $tracking_code = $_POST['tracking'];

    if ( wc_correios_update_tracking_code( $order_id, $tracking_code ) ) {
      wp_send_json_success();
    } else {

      $return = array(
        'message' => 'error',
      );

      wp_send_json_error( $return );
    }

    die();

  }

}
add_action( 'plugins_loaded', array( 'WC_Correios_Tracking_Code_Column', 'get_instance' ) );
