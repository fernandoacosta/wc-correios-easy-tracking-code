<?php
/**
 * Testes do salvamento do código de rastreio pela lista de pedidos (AJAX `wc_add_correios_tracking`).
 *
 * Rodar numa loja local com WooCommerce, WooCommerce Correios e este plugin ativos:
 *
 *   wp --path=<loja> eval-file ~/dev/wc-correios-easy-tracking-code/tests/ajax.php
 *
 * Cria pedidos e usuários de teste e apaga no fim. Nenhum e-mail sai (pre_wp_mail).
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) ) {
	exit;
}

$failures = 0;
$check    = function ( $label, $ok ) use ( &$failures ) {
	if ( $ok ) {
		WP_CLI::log( "ok    {$label}" );
	} else {
		$failures++;
		WP_CLI::warning( "FALHA {$label}" );
	}
};

if ( ! function_exists( 'wc_correios_update_tracking_code' ) ) {
	WP_CLI::error( 'O WooCommerce Correios precisa estar ativo.' );
}

add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter(
	'wp_die_ajax_handler',
	function () {
		return function ( $message = '', $title = '', $args = array() ) {
			throw new RuntimeException( 'die:' . ( is_scalar( $message ) ? $message : '' ) . ':' . ( is_array( $args ) ? (int) ( $args['response'] ?? 0 ) : (int) $args ) );
		};
	}
);

// Chama a ação como o admin-ajax.php: `wp_ajax_` logado, `wp_ajax_nopriv_` sem login.
$call = function ( array $post ) {
	$_POST    = $post;
	$_REQUEST = $post;
	$hook     = ( is_user_logged_in() ? 'wp_ajax_' : 'wp_ajax_nopriv_' ) . 'wc_add_correios_tracking';
	ob_start();
	$status = http_response_code();
	try {
		if ( has_action( $hook ) ) {
			do_action( $hook );
		}
		$end = 'sem-handler';
	} catch ( Throwable $e ) {
		$end = $e->getMessage();
	}
	$body = (string) ob_get_clean();
	$_POST = $_REQUEST = array();
	return array( 'end' => $end, 'body' => $body, 'json' => json_decode( $body, true ), 'status' => http_response_code() );
};

$codes = function ( $id ) {
	return (string) wc_get_order( $id )->get_meta( '_correios_tracking_code' );
};

$order = wc_create_order();
$order->set_billing_email( 'cliente-teste@example.com' );
$order->save();
$id = $order->get_id();

$manager  = wp_insert_user( array( 'user_login' => 'rastreio-gerente-' . wp_generate_password( 5, false ), 'user_pass' => wp_generate_password(), 'role' => 'shop_manager' ) );
$customer = wp_insert_user( array( 'user_login' => 'rastreio-cliente-' . wp_generate_password( 5, false ), 'user_pass' => wp_generate_password(), 'role' => 'customer' ) );

// 1. Visitante sem login: nada muda.
wp_set_current_user( 0 );
$r = $call( array( 'action' => 'wc_add_correios_tracking', 'order_id' => $id, 'tracking' => 'XX123456789BR' ) );
$check( 'visitante sem login não tem handler (nopriv fora)', false === has_action( 'wp_ajax_nopriv_wc_add_correios_tracking' ) );
$check( 'visitante sem login não grava o código', '' === $codes( $id ) );

// Nonce de quem está logado.
wp_set_current_user( $manager );
$nonce = wp_create_nonce( 'wc_add_correios_tracking' );

// 2. Gerente da loja sem nonce: recusado.
$r = $call( array( 'action' => 'wc_add_correios_tracking', 'order_id' => $id, 'tracking' => 'XX123456789BR' ) );
$check( 'gerente sem nonce: recusado (-1)', 0 === strpos( $r['end'], 'die:-1' ) && '' === $codes( $id ) );

// 3. Cliente logado com nonce dele: sem permissão.
wp_set_current_user( $customer );
$r = $call( array( 'action' => 'wc_add_correios_tracking', 'order_id' => $id, 'tracking' => 'XX123456789BR', 'security' => wp_create_nonce( 'wc_add_correios_tracking' ) ) );
$check( 'cliente logado: sem permissão (403)', 403 === $r['status'] && false === ( $r['json']['success'] ?? null ) && '' === $codes( $id ) );

// 4. Gerente com nonce: grava, com espaços e minúsculas limpos.
wp_set_current_user( $manager );
$r = $call( array( 'action' => 'wc_add_correios_tracking', 'order_id' => $id, 'tracking' => '  xx123456789br ', 'security' => $nonce ) );
$check( 'gerente com nonce: grava', true === ( $r['json']['success'] ?? null ) && 'XX123456789BR' === $codes( $id ) );
$check( 'resposta traz os códigos do pedido', array( 'XX123456789BR' ) === ( $r['json']['data']['codes'] ?? null ) );

// 5. Pedido que não existe: 404, sem erro fatal.
$r = $call( array( 'action' => 'wc_add_correios_tracking', 'order_id' => 999999999, 'tracking' => 'XX1', 'security' => $nonce ) );
$check( 'pedido inexistente: 404 sem fatal', 404 === $r['status'] && false === ( $r['json']['success'] ?? null ) );

// 6. Vazio não apaga os códigos por engano.
$r = $call( array( 'action' => 'wc_add_correios_tracking', 'order_id' => $id, 'tracking' => '   ', 'security' => $nonce ) );
$check( 'código vazio é recusado e não apaga', false === ( $r['json']['success'] ?? null ) && 'XX123456789BR' === $codes( $id ) );

// 7. Mesmo código de novo: recusado. Segundo código: soma.
$r = $call( array( 'action' => 'wc_add_correios_tracking', 'order_id' => $id, 'tracking' => 'XX123456789BR', 'security' => $nonce ) );
$check( 'código repetido é recusado', false === ( $r['json']['success'] ?? null ) && 'XX123456789BR' === $codes( $id ) );
$r = $call( array( 'action' => 'wc_add_correios_tracking', 'order_id' => $id, 'tracking' => 'YY987654321BR', 'security' => $nonce ) );
$check( 'segundo código soma', array( 'XX123456789BR', 'YY987654321BR' ) === ( $r['json']['data']['codes'] ?? null ) );
$check( 'resposta traz o HTML da lista', false !== strpos( (string) ( $r['json']['data']['html'] ?? '' ), 'data-code="YY987654321BR"' ) );

// 8. Tirar um código.
$r = $call( array( 'action' => 'wc_add_correios_tracking', 'order_id' => $id, 'tracking' => 'XX123456789BR', 'remove' => '1', 'security' => $nonce ) );
$check( 'tirar um código deixa o outro', array( 'YY987654321BR' ) === ( $r['json']['data']['codes'] ?? null ) && 'YY987654321BR' === $codes( $id ) );
$r = $call( array( 'action' => 'wc_add_correios_tracking', 'order_id' => $id, 'tracking' => 'ZZ000', 'remove' => '1', 'security' => $nonce ) );
$check( 'tirar código que não está no pedido: recusado', false === ( $r['json']['success'] ?? null ) );

// 9. Coluna "Rastreio" nas duas listas, para quem edita pedidos.
$plugin = WC_Correios_Tracking_Code_Column::get_instance();
$cols   = $plugin->add_column( array( 'cb' => '', 'order_number' => 'Pedido', 'order_status' => 'Status', 'order_total' => 'Total' ) );
$check( 'coluna depois do status', array( 'cb', 'order_number', 'order_status', 'wc_correios_tracking', 'order_total' ) === array_keys( $cols ) );
$check( 'coluna nas listas com e sem HPOS', false !== has_filter( 'manage_woocommerce_page_wc-orders_columns', array( $plugin, 'add_column' ) ) && false !== has_filter( 'manage_edit-shop_order_columns', array( $plugin, 'add_column' ) ) );
wp_set_current_user( $customer );
$check( 'cliente não vê a coluna', ! isset( $plugin->add_column( array( 'order_status' => 'Status' ) )['wc_correios_tracking'] ) );
wp_set_current_user( $manager );

// 10. Saída escapada na célula.
$order = wc_get_order( $id );
$order->update_meta_data( '_correios_tracking_code', '" autofocus onfocus="alert(1)' );
$order->save();
ob_start();
do_action( 'manage_woocommerce_page_wc-orders_custom_column', 'wc_correios_tracking', wc_get_order( $id ) );
$html = (string) ob_get_clean();
$check( 'célula renderizada (HPOS)', false !== strpos( $html, 'class="wcet no-link" data-order-id="' . $id . '"' ) );
ob_start();
do_action( 'manage_shop_order_posts_custom_column', 'wc_correios_tracking', $id );
$check( 'célula renderizada (lista sem HPOS)', false !== strpos( (string) ob_get_clean(), 'data-order-id="' . $id . '"' ) );
$check( 'saída do código escapada (sem atributo injetado)', false === strpos( $html, '" autofocus' ) && false !== strpos( $html, '&quot; autofocus' ) );

// Limpeza.
wp_set_current_user( 0 );
wc_get_order( $id )->delete( true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $manager );
wp_delete_user( $customer );

if ( $failures ) {
	WP_CLI::error( "{$failures} falha(s)." );
}
WP_CLI::success( 'Rastreio pela lista de pedidos ok.' );
