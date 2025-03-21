<?php
if (! defined('ABSPATH')) {
	exit; //Exit if accessed directly
}

/* Return of the HelloAsso API */

add_action('woocommerce_api_helloasso', 'helloasso_endpoint');
function helloasso_endpoint()
{
	if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'helloasso_connect_return')) {
		wp_safe_redirect(get_site_url());
		exit;
	} else {
		$nonceRequest = sanitize_text_field(wp_unslash($_GET['nonce']));
	}

	$isInTestMode = get_option('helloasso_testmode');

	if ('yes' === $isInTestMode) {
		$client_id = HELLOASSO_WOOCOMMERCE_CLIENT_ID_TEST;
		$client_secret = HELLOASSO_WOOCOMMERCE_CLIENT_SECRET_TEST;
		$api_url = HELLOASSO_WOOCOMMERCE_API_URL_TEST;
	} else {
		$client_id = HELLOASSO_WOOCOMMERCE_CLIENT_ID_PROD;
		$client_secret = HELLOASSO_WOOCOMMERCE_CLIENT_SECRET_PROD;
		$api_url = HELLOASSO_WOOCOMMERCE_API_URL_PROD;
	}

	$nonce = wp_create_nonce('helloasso_connect');

	if (!isset($_GET['code']) || !isset($_GET['state'])) {
		wp_safe_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=error_connect&nonce=' . $nonce);
		exit;
	}

	$code = sanitize_text_field($_GET['code']);
	$state = sanitize_text_field($_GET['state']);

	if (get_option('helloasso_state') !== $state) {

		wp_safe_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=error_connect&nonce=' . $nonce);
		exit;
	}

	$url = $api_url . 'oauth2/token';

	$data = array(
		'client_id' => $client_id,
		'client_secret' => $client_secret,
		'grant_type' => 'authorization_code',
		'code' => $code,
		'redirect_uri' => get_site_url() . '/wc-api/helloasso?nonce=' . $nonceRequest,
		'code_verifier' => get_option('helloasso_code_verifier')
	);

	$response = wp_remote_post($url, helloasso_get_args_post_urlencode($data));

	$status_code = wp_remote_retrieve_response_code($response);
	if (200 !== $status_code) {
		wp_safe_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=error_connect&status_code=' . $status_code . '&nonce=' . $nonce);
		exit;
	}

	$response_body = wp_remote_retrieve_body($response);
	$data = json_decode($response_body);

	if (isset($data->access_token)) {
		delete_option('helloasso_access_token_asso');
		delete_option('helloasso_refresh_token_asso');
		delete_option('helloasso_token_expires_in_asso');
		delete_option('helloasso_refresh_token_expires_in_asso');
		delete_option('helloasso_organization_slug');
		add_option('helloasso_organization_slug', $data->organization_slug);
		add_option('helloasso_access_token_asso', $data->access_token);
		add_option('helloasso_refresh_token_asso', $data->refresh_token);
		add_option('helloasso_token_expires_in_asso', $data->expires_in);
		add_option('helloasso_refresh_token_expires_in_asso', time() + 2629800);

		$urlNotif = $api_url . 'v5/partners/me/api-notifications/organizations/' . $data->organization_slug;

		$dataNotifSend = array(
			'url' => get_site_url() . '/wc-api/helloasso_webhook'
		);

		$responseNotif = wp_remote_request($urlNotif, helloasso_get_args_put_token($dataNotifSend, $data->access_token));

		$status_code = wp_remote_retrieve_response_code($responseNotif);
		if (200 !== $status_code) {
			$gateway_settings = get_option('woocommerce_helloasso_settings', array());
			$gateway_settings['enabled'] = 'no';
			update_option('woocommerce_helloasso_settings', $gateway_settings);

			delete_option('helloasso_code_verifier');
			delete_option('helloasso_state');
			delete_option('helloasso_authorization_url');
			delete_option('helloasso_organization_slug');
			delete_option('helloasso_access_token_asso');
			delete_option('helloasso_refresh_token_asso');
			delete_option('helloasso_token_expires_in_asso');
			delete_option('helloasso_refresh_token_expires_in_asso');

			wp_safe_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=error_connect&status_code=' . $status_code . '&nonce=' . $nonce);
			exit;
		}

		delete_option('helloasso_webhook_url');
		add_option('helloasso_webhook_url', get_site_url() . '/wc-api/helloasso_webhook');

		wp_safe_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=success_connect&nonce=' . $nonce);
		exit;
	}

	exit;
}

add_action('woocommerce_api_helloasso_deco', 'helloasso_endpoint_deco');
function helloasso_endpoint_deco()
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
	echo wp_json_encode(array('success' => true, 'message' => 'Vous avez bien été déconnecté de votre compte HelloAsso'));
	exit;
}

add_action('woocommerce_api_helloasso_webhook', 'helloasso_endpoint_webhook');
function helloasso_endpoint_webhook()
{
	$raw_input = file_get_contents('php://input');
	$data = json_decode($raw_input, true);

	add_option('helloasso_webhook_data', wp_json_encode($data));

	if ('Order' === $data['eventType']) {
		validate_order($data['metadata']['reference'], $data['data']['checkoutIntentId']);
	} else if ('Organization' === $data['eventType']) {
		delete_option('helloasso_organization_slug');
		add_option('helloasso_organization_slug', $data['data']['new_slug_organization']);

		helloasso_refresh_token_asso();
	}

	exit;
}

add_action('woocommerce_api_helloasso_order', 'helloasso_endpoint_order');
function helloasso_endpoint_order()
{
	if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'helloasso_order')) {
		wp_safe_redirect(get_site_url());
		exit;
	}

	if (isset($_GET['type']) && isset($_GET['order_id'])) {
		$order_id = sanitize_text_field($_GET['order_id']);
		$checkoutIntentId = sanitize_text_field($_GET['checkoutIntentId']);

		$order = validate_order($order_id, $checkoutIntentId);

		wp_safe_redirect($order->get_checkout_order_received_url());
	}
}

function validate_order($orderId, $checkoutIntentId)
{
	$order = wc_get_order($orderId);
	if (!$order) {
		exit;
	}

	$isInTestMode = get_option('helloasso_testmode');
	if ('yes' === $isInTestMode) {
		$api_url = HELLOASSO_WOOCOMMERCE_API_URL_TEST;
	} else {
		$api_url = HELLOASSO_WOOCOMMERCE_API_URL_PROD;
	}

	helloasso_refresh_token_asso();

	$slug = get_option('helloasso_organization_slug');
	$helloasso_access_token_asso = get_option('helloasso_access_token_asso');

	$response = wp_remote_request($api_url . 'v5/organizations/' . $slug . '/checkout-intents/' . $checkoutIntentId, helloasso_get_args_get_token($helloasso_access_token_asso));
	$body = wp_remote_retrieve_body($response);
	$haOrder = json_decode($body);

	if ($haOrder->order->payments[0]->state == 'Authorized') {
		$order->update_status('processing');
	} else if ($haOrder->order->payments[0]->state == 'Refused') {
		$order->update_status('failed');
	}

	return $order;
}
