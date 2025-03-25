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
		$multi_enabled = isset($this->settings['multi_enabled']) && $this->settings['multi_enabled'] === 'yes';

		$payment_choices = ['one_time'];
		if ($multi_enabled) {
			$payment_choices[] = 'three_times';
		}

		return [
			'title' => $this->gateway->title,
			'description' => $this->gateway->description,
			'multi_enabled' => $multi_enabled,
			'payment_choices' => $payment_choices
		];
	}
}
