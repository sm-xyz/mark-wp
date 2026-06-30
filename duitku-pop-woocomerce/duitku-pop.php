<?php
/**
 * Plugin Name: Duitku Pop Payment Gateway
 * 
 * Description:  Duitku Payment Gateway payment channel selection on payment page
 * Version: 1.0.3
 *
 * Author: Duitku Development Team
 * Author URI: https://www.duitku.com/
 *
 * 
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Duitku_Pop_Payments {
	public static function init(){

	//function to declare compatibilty with custom order tables (HPOS)
		add_action('before_woocommerce_init', function(){
			if(class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class)){
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					'custom_order_tables', __FILE__, true );
			}
		});
		add_action('plugins_loaded', array(__CLASS__, 'woocommerce_duitku_pop_init'), 0);
		add_action('woocommerce_blocks_loaded', array(__CLASS__, 'woocommerce_gateway_duitku_woocommerce_block_support'));
	}

	public static function woocommerce_duitku_pop_init(){
		if(!class_exists('WC_Payment_Gateway')){
			return;
		}

		include_once dirname(__FILE__) . '/includes/duitku/wc-gateway-duitku-sanitized.php';
		include_once dirname(__FILE__) . '/includes/duitku/wc-gateway-duitku-validation.php';

		if(!class_exists('WC_Gateway_Duitku')){
			include dirname(__FILE__) . '/class/class.duitku-pop.php';
		}
		add_filter('woocommerce_payment_gateways', 'add_duitku_pop');

		function add_duitku_pop($methods) {
			$methods[] = 'WC_Gateway_Duitku_Pop';
			return $methods;
		}
	}

	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	public static function plugin_abspath() {
		return trailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/*
	* function to declare compatibility with cart checkout blocks
	*/
	public static function woocommerce_gateway_duitku_woocommerce_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once 'includes/blocks/class-wc-duitku-payments-pop-blocks.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Gateway_Duitku_Pop_Blocks_Support() );
				}
			);
		}
	}
}
WC_Duitku_Pop_Payments::init();

?>
