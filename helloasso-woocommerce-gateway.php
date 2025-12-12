<?php

/**
 * Plugin Name:       HelloAsso Payments for WooCommerce
 * Description:       Recevez 100% de vos paiements gratuitement. HelloAsso est la seule solution de paiement gratuite du secteur associatif. Nous sommes financés librement par la solidarité de celles et ceux qui choisissent de laisser une contribution volontaire au moment du paiement à une association.
 * Version:           1.1.0
 * Requires at least: 5.0
 * WC requires at least: 7.7
 * Requires PHP:      7.2.34
 * Requires Plugins:  woocommerce
 * Author:            HelloAsso
 * Author URI:        https://helloasso.com
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */


if (!defined('ABSPATH')) {
	exit; //Exit if accessed directly
}

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Helloasso\HelloassoPaymentsForWoocommerce\Gateway\WC_HelloAsso_Gateway;

require_once('helper/helloasso-woocommerce-api-call.php');
require_once('helper/helloasso-woocommerce-config.php');
require_once('helper/helloasso-woocommerce-helper.php');
require_once('cron/helloasso-woocommerce-cron.php');
require_once('helloasso-api/helloasso-woocommerce-api.php');
require_once('wc-api/helloasso-woocommerce-wc-api.php');


function helloasso_declare_cart_checkout_blocks_compatibility()
{
	if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
		FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
	}
}

add_action('before_woocommerce_init', 'helloasso_declare_cart_checkout_blocks_compatibility');

add_action('woocommerce_blocks_loaded', 'helloasso_register_order_approval_payment_method_type', 20);


function helloasso_register_order_approval_payment_method_type()
{
	if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
		return;
	}
	require_once plugin_dir_path(__FILE__) . 'block/helloasso-woocommerce-blocks.php';

	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function (PaymentMethodRegistry $payment_method_registry) {
			// Register an instance of Helloasso_Blocks
			$payment_method_registry->register(new Helloasso_Blocks());
		}
	);
}

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'helloasso_add_gateway_class');
function helloasso_add_gateway_class($gateways)
{
    $gateway_class = \Helloasso\HelloassoPaymentsForWoocommerce\Gateway\WC_HelloAsso_Gateway::class;
    
    // Vérifier que la classe existe et n'est pas déjà ajoutée
    if ( class_exists( $gateway_class ) && ! in_array( $gateway_class, $gateways, true ) ) {
        $gateways[] = $gateway_class;
    }
    
    return $gateways;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'helloasso_woocommerce_actions_links');
function helloasso_woocommerce_actions_links($links)
{
	$links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=helloasso') . '">' . __('Réglages', 'woocommerce-helloasso') . '</a>';
	return $links;
}

register_activation_hook(__FILE__, 'helloasso_activate');
function helloasso_activate()
{
	if (!class_exists('WooCommerce')) {
		deactivate_plugins(plugin_basename(__FILE__));
		wp_die('Ce plugin nécessite WooCommerce pour fonctionner');
	}

	$currency = get_woocommerce_currency();
	if ($currency !== 'EUR') {
		deactivate_plugins(plugin_basename(__FILE__));
		wp_die('HelloAsso ne prend en charge que les paiements en euros. Veuillez changer la devise de votre boutique en euros pour activer ce plugin.');
	}
}


register_deactivation_hook(__FILE__, 'helloasso_deactivate');
function helloasso_deactivate()
{
	delete_option('helloasso_access_token');
	delete_option('helloasso_refresh_token');
	delete_option('helloasso_token_expires_in');
	delete_option('helloasso_refresh_token_expires_in');
	delete_option('helloasso_code_verifier');
	delete_option('helloasso_state');
	delete_option('helloasso_authorization_url');
	delete_option('helloasso_organization_slug');
	delete_option('helloasso_access_token_asso');
	delete_option('helloasso_refresh_token_asso');
	delete_option('helloasso_token_expires_in_asso');
	delete_option('helloasso_refresh_token_expires_in_asso');
	delete_option('helloasso_webhook_url');
	delete_option('helloasso_testmode');
	delete_option('woocommerce_helloasso_settings');
	delete_option('helloasso_webhook_data');
}

add_action('wp_ajax_helloasso_deco', 'helloasso_deco');

