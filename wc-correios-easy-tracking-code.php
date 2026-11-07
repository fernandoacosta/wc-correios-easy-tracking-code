<?php
/*
 * Plugin Name: WC Correios Easy Tracking Code
 * Description: Adicione código de rastreio dos Correios sem precisar abrir o pedido no WooCommerce.
 * Plugin URI: http://fernandoacosta.net
 * Author: Fernando Acosta
 * Author URI: http://fernandoacosta.net
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce, woocommerce-correios
 * Tested up to: 7.1
 * WC requires at least: 8.0.0
 * WC tested up to:      11.2.2
 * Text Domain: wc-correios-easy-tracking-code
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

defined( 'ABSPATH' ) || exit;

/**
 * Coluna "Rastreio" na lista de pedidos (com e sem HPOS): os códigos dos Correios do pedido, com link, e um campo
 * para adicionar ou tirar um código sem abrir o pedido.
 *
 * Quem grava é o WooCommerce Correios (`wc_correios_update_tracking_code`): nota no pedido e e-mail para a cliente,
 * como no pedido aberto. A coluna aparece para quem pode editar pedidos e só em pedido (não em reembolso).
 */
class WC_Correios_Tracking_Code_Column {
  const VERSION = '2.0.0';
  const COLUMN  = 'wc_correios_tracking';
  const ACTION  = 'wc_add_correios_tracking';
  const SCREENS = array( 'edit-shop_order', 'woocommerce_page_wc-orders' );

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
    // Lista de pedidos sem HPOS (posts) e com HPOS (tabela de pedidos).
    add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column' ), 20 );
    add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_column_for_post' ), 10, 2 );
    add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_column' ), 20 );
    add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_column' ), 10, 2 );

    add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts' ) );

    // Só quem está logado: a permissão é conferida no handler.
    add_action( 'wp_ajax_' . self::ACTION, array( $this, 'wc_add_correios_tracking' ) );
  }

  /**
   * Return an instance of this class.
   *
   * @return object A single instance of this class.
   */
  public static function get_instance() {
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

  /**
   * A coluna existe para quem pode editar pedidos e com o WooCommerce Correios ativo.
   *
   * @return bool
   */
  protected function available() {
    return function_exists( 'wc_correios_update_tracking_code' ) && current_user_can( 'edit_shop_orders' );
  }

  /**
   * Coluna "Rastreio", depois do status.
   *
   * @param array $columns Colunas.
   *
   * @return array
   */
  public function add_column( $columns ) {
    if ( ! $this->available() ) {
      return $columns;
    }

    $added = array();
    foreach ( $columns as $key => $label ) {
      $added[ $key ] = $label;
      if ( 'order_status' === $key ) {
        $added[ self::COLUMN ] = __( 'Rastreio', 'wc-correios-easy-tracking-code' );
      }
    }

    if ( ! isset( $added[ self::COLUMN ] ) ) {
      $added[ self::COLUMN ] = __( 'Rastreio', 'wc-correios-easy-tracking-code' );
    }

    return $added;
  }

  /**
   * Lista sem HPOS: o WordPress passa o id do post.
   *
   * @param string $column  Coluna.
   * @param int    $post_id Pedido.
   */
  public function render_column_for_post( $column, $post_id ) {
    if ( self::COLUMN === $column ) {
      $this->render_column( $column, wc_get_order( $post_id ) );
    }
  }

  /**
   * Célula: códigos do pedido, com link e botão de tirar, e o campo de adicionar.
   *
   * @param string         $column Coluna.
   * @param WC_Order|false $order  Pedido.
   */
  public function render_column( $column, $order ) {
    if ( self::COLUMN !== $column || ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() || ! $this->available() ) {
      return;
    }

    echo $this->cell_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado e escapado em cell_html().
  }

  /**
   * HTML da célula.
   *
   * @param WC_Order $order Pedido.
   *
   * @return string
   */
  public function cell_html( $order ) {
    /* translators: %s: número do pedido */
    $label = sprintf( __( 'Código de rastreio do pedido %s', 'wc-correios-easy-tracking-code' ), $order->get_order_number() );

    return sprintf(
      '<div class="wcet no-link" data-order-id="%1$d"><ul class="wcet__codes">%2$s</ul><div class="wcet__add"><input type="text" class="wcet__input" placeholder="%3$s" aria-label="%4$s" autocomplete="off" spellcheck="false" maxlength="40" /><button type="button" class="button wcet__save">%5$s</button></div><span class="wcet__status" role="status" aria-live="polite"></span></div>',
      absint( $order->get_id() ),
      $this->codes_html( self::get_codes( $order ) ),
      esc_attr__( 'Ex.: AA123456789BR', 'wc-correios-easy-tracking-code' ),
      esc_attr( $label ),
      esc_html__( 'Adicionar', 'wc-correios-easy-tracking-code' )
    );
  }

  /**
   * Itens da lista de códigos.
   *
   * @param string[] $codes Códigos.
   *
   * @return string
   */
  public function codes_html( array $codes ) {
    $html = '';

    foreach ( $codes as $code ) {
      $url  = self::tracking_url( $code );
      $text = esc_html( $code );

      /* translators: %s: código de rastreio */
      $remove = sprintf( __( 'Tirar o código %s', 'wc-correios-easy-tracking-code' ), $code );

      $html .= sprintf(
        '<li class="wcet__code">%1$s<button type="button" class="wcet__remove" data-code="%2$s" aria-label="%3$s" title="%3$s">&times;</button></li>',
        $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . $text . '</a>' : $text,
        esc_attr( $code ),
        esc_attr( $remove )
      );
    }

    return $html;
  }

  /**
   * Códigos do pedido (o WooCommerce Correios guarda separados por vírgula).
   *
   * @param WC_Order $order Pedido.
   *
   * @return string[]
   */
  public static function get_codes( $order ) {
    return array_values( array_filter( array_map( 'trim', explode( ',', (string) $order->get_meta( '_correios_tracking_code' ) ) ) ) );
  }

  /**
   * Link de rastreio do WooCommerce Correios; vazio se ele não devolver um endereço.
   *
   * @param string $code Código.
   *
   * @return string
   */
  public static function tracking_url( $code ) {
    $url = function_exists( 'wc_correios_get_tracking_url' ) ? (string) wc_correios_get_tracking_url( $code ) : '';

    return preg_match( '#^https?://#i', $url ) ? $url : '';
  }

  /**
   * CSS e JS só na lista de pedidos (com e sem HPOS).
   *
   * @param string $hook Tela.
   */
  public function add_scripts( $hook = '' ) {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

    if ( ! $screen || ! in_array( $screen->id, self::SCREENS, true ) || ! $this->available() ) {
      return;
    }

    $url = plugins_url( 'assets/', $this->get_main_file() );

    wp_enqueue_style( 'wc-correios-easy-tracking-code-css', $url . 'wc-correios-easy-tracking-code.css', array(), self::VERSION );
    wp_enqueue_script( 'wc-correios-easy-tracking-code-js', $url . 'wc-correios-easy-tracking-code.js', array(), self::VERSION, true );
    wp_localize_script(
      'wc-correios-easy-tracking-code-js',
      'wcCorreiosEasyTracking',
      array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( self::ACTION ),
        'texts'    => array(
          'saving'  => __( 'Salvando…', 'wc-correios-easy-tracking-code' ),
          'saved'   => __( 'Salvo. A cliente recebe o e-mail.', 'wc-correios-easy-tracking-code' ),
          'removed' => __( 'Código tirado.', 'wc-correios-easy-tracking-code' ),
          'empty'   => __( 'Digite o código de rastreio.', 'wc-correios-easy-tracking-code' ),
          'error'   => __( 'Não deu para salvar. Tente de novo ou abra o pedido.', 'wc-correios-easy-tracking-code' ),
          'confirm' => __( 'Tirar este código de rastreio do pedido?', 'wc-correios-easy-tracking-code' ),
        ),
      )
    );
  }

  /**
   * Adiciona (ou, com `remove`, tira) um código de rastreio. Responde com os códigos e o HTML da lista.
   */
  public function wc_add_correios_tracking() {
    check_ajax_referer( self::ACTION, 'security' );

    if ( ! current_user_can( 'edit_shop_orders' ) ) {
      wp_send_json_error( array( 'message' => __( 'Você não tem permissão para editar pedidos.', 'wc-correios-easy-tracking-code' ) ), 403 );
    }

    if ( ! function_exists( 'wc_correios_update_tracking_code' ) ) {
      wp_send_json_error( array( 'message' => __( 'Plugin WooCommerce Correios não está ativo', 'wc-correios-easy-tracking-code' ) ) );
    }

    $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
    $order    = $order_id ? wc_get_order( $order_id ) : false;

    if ( ! $order || 'shop_order' !== $order->get_type() ) {
      wp_send_json_error( array( 'message' => __( 'Pedido não encontrado.', 'wc-correios-easy-tracking-code' ) ), 404 );
    }

    // Código dos Correios: letras e números, sem espaços, em maiúsculas. Vazio não apaga os códigos do pedido.
    $tracking_code = isset( $_POST['tracking'] ) ? strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( wp_unslash( $_POST['tracking'] ) ) ) ) : '';
    $remove        = ! empty( $_POST['remove'] );

    if ( '' === $tracking_code ) {
      wp_send_json_error( array( 'message' => __( 'Digite o código de rastreio.', 'wc-correios-easy-tracking-code' ) ), 400 );
    }

    if ( $remove && ! in_array( $tracking_code, self::get_codes( $order ), true ) ) {
      wp_send_json_error( array( 'message' => __( 'Este código não está no pedido.', 'wc-correios-easy-tracking-code' ) ), 400 );
    }

    if ( ! $remove && in_array( $tracking_code, self::get_codes( $order ), true ) ) {
      wp_send_json_error( array( 'message' => __( 'Este código já está no pedido.', 'wc-correios-easy-tracking-code' ) ), 400 );
    }

    if ( wc_correios_update_tracking_code( $order, $tracking_code, $remove ) ) {
      $codes = self::get_codes( wc_get_order( $order_id ) );
      wp_send_json_success(
        array(
          'codes' => $codes,
          'html'  => $this->codes_html( $codes ),
        )
      );
    }

    wp_send_json_error( array( 'message' => __( 'Não foi possível salvar o código.', 'wc-correios-easy-tracking-code' ) ) );
  }
}

add_action( 'plugins_loaded', array( 'WC_Correios_Tracking_Code_Column', 'get_instance' ) );

// Compatível com a tabela de pedidos do WooCommerce (HPOS) e com o carrinho e o checkout em blocos.
add_action( 'before_woocommerce_init', function () {
  if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
  }
} );
