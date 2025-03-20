<?php
if (! defined('ABSPATH')) {
	exit; //Exit if accessed directly
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Helloasso_Blocks extends AbstractPaymentMethodType
{

	private $gateway;
	protected $name = 'helloasso';

	public function initialize()
	{
		$this->settings = get_option('woocommerce_helloasso_settings', []);
		$this->gateway = new WC_HelloAsso_Gateway();
	}

	public function is_active()
	{
		return $this->gateway->is_available();
	}

	public function get_payment_method_script_handles()
	{

		wp_register_script(
			'wc-helloasso-blocks-integration',
			plugin_dir_url(__FILE__) . 'checkout.js',
			[
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			],
			'1.0.0',
			true
		);

		return ['wc-helloasso-blocks-integration'];
	}

	public function get_payment_method_data()
	{
		return [
			'title' => $this->gateway->title,
			/*'description' => '
		   <div style="display: flex; align-items: center;">
		   <img style="max-width: 50px; height:auto; margin-right: 8px;" src="assets/logo-ha.png" alt="HelloAsso Logo">
			<p>' . wp_kses_post($this->gateway->description) . '</p>
			</div>',,*/
			'description' => $this->gateway->description,
		];
	}
}
