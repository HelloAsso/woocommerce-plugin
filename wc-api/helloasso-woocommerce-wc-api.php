<?php
if (! defined('ABSPATH')) {
	exit; //Exit if accessed directly
}

add_action('woocommerce_api_helloasso', 'helloasso_endpoint');
function helloasso_endpoint()
{
	helloasso_log_info('Endpoint HelloAsso appelé', array(
		'endpoint' => 'helloasso',
		'get_params' => $_GET
	));

	if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'helloasso_connect_return')) {
		helloasso_log_error('Nonce invalide dans endpoint helloasso', array('get_params' => $_GET));
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
		helloasso_log_error('Code ou state manquant dans endpoint helloasso', array('get_params' => $_GET));
		wp_safe_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=error_connect&nonce=' . $nonce);
		exit;
	}

	$code = sanitize_text_field($_GET['code']);
	$state = sanitize_text_field($_GET['state']);

	if (get_option('helloasso_state') !== $state) {
		helloasso_log_error('State invalide dans endpoint helloasso', array(
			'received_state' => $state,
			'expected_state' => get_option('helloasso_state')
		));
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

	helloasso_log_info('Demande de token OAuth2', array(
		'url' => $url,
		'test_mode' => $isInTestMode
	));

	$response = wp_remote_post($url, helloasso_get_args_post_urlencode($data));

	$status_code = wp_remote_retrieve_response_code($response);
	if (200 !== $status_code) {
		helloasso_log_error('Erreur lors de la demande de token OAuth2', array(
			'status_code' => $status_code,
			'response_body' => wp_remote_retrieve_body($response)
		));
		wp_safe_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=error_connect&status_code=' . $status_code . '&nonce=' . $nonce);
		exit;
	}

	$response_body = wp_remote_retrieve_body($response);
	$data = json_decode($response_body);

	if (isset($data->access_token)) {
		helloasso_log_info('Token OAuth2 reçu avec succès', array(
			'organization_slug' => $data->organization_slug ?? 'unknown',
			'expires_in' => $data->expires_in ?? 'unknown'
		));

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

		helloasso_log_info('Configuration du webhook', array(
			'webhook_url' => $dataNotifSend['url'],
			'organization_slug' => $data->organization_slug
		));

		$responseNotif = wp_remote_request($urlNotif, helloasso_get_args_put_token($dataNotifSend, $data->access_token));

		$status_code = wp_remote_retrieve_response_code($responseNotif);
		if (200 !== $status_code) {
			helloasso_log_error('Erreur lors de la configuration du webhook', array(
				'status_code' => $status_code,
				'response_body' => wp_remote_retrieve_body($responseNotif)
			));

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

		helloasso_log_info('Connexion HelloAsso réussie', array(
			'organization_slug' => $data->organization_slug
		));

		wp_safe_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=success_connect&nonce=' . $nonce);
		exit;
	}

	exit;
}

add_action('woocommerce_api_helloasso_deco', 'helloasso_endpoint_deco');
function helloasso_endpoint_deco()
{
	$gateway_settings = get_option('woocommerce_helloasso_settings', array());
	$gateway_settings['enabled'] = 'no';
	$gateway_settings['multi_3_enabled'] = 'no';
	$gateway_settings['multi_12_enabled'] = 'no';
	$gateway_settings['testmode'] = 'no';
	update_option('woocommerce_helloasso_settings', $gateway_settings);

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

	helloasso_log_info('Webhook HelloAsso reçu', array(
		'event_type' => $data['eventType'] ?? 'unknown',
		'raw_data_length' => strlen($raw_input)
	));

	add_option('helloasso_webhook_data', wp_json_encode($data));

	if ('Order' === $data['eventType']) {
		helloasso_log_info('Traitement d\'un événement Order', array(
			'order_reference' => $data['metadata']['reference'] ?? 'unknown',
			'checkout_intent_id' => $data['data']['checkoutIntentId'] ?? 'unknown'
		));
		validate_order($data['metadata']['reference'], $data['data']['checkoutIntentId']);
	} else if ('Organization' === $data['eventType']) {
		helloasso_log_info('Traitement d\'un événement Organization', array(
			'new_slug' => $data['data']['new_slug_organization'] ?? 'unknown'
		));
		delete_option('helloasso_organization_slug');
		add_option('helloasso_organization_slug', $data['data']['new_slug_organization']);

		helloasso_refresh_token_asso();
	} else {
		helloasso_log_warning('Événement webhook non reconnu', array(
			'event_type' => $data['eventType'] ?? 'unknown'
		));
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
	helloasso_log_info('Début de validation de commande', array(
		'order_id' => $orderId,
		'checkout_intent_id' => $checkoutIntentId
	));

	$order = wc_get_order($orderId);
	if (!$order) {
		helloasso_log_error('Commande introuvable lors de la validation', array('order_id' => $orderId));
		exit;
	}

	helloasso_log_info('Commande trouvée', array(
		'order_id' => $orderId,
		'order_status' => $order->get_status(),
		'order_total' => $order->get_total()
	));

	$isInTestMode = get_option('helloasso_testmode');
	if ('yes' === $isInTestMode) {
		$api_url = HELLOASSO_WOOCOMMERCE_API_URL_TEST;
	} else {
		$api_url = HELLOASSO_WOOCOMMERCE_API_URL_PROD;
	}

	helloasso_refresh_token_asso();

	$slug = get_option('helloasso_organization_slug');
	$helloasso_access_token_asso = get_option('helloasso_access_token_asso');

	helloasso_log_info('Récupération des détails de la commande HelloAsso', array(
		'order_id' => $orderId,
		'organization_slug' => $slug,
		'api_url' => $api_url,
		'has_token' => !empty($helloasso_access_token_asso)
	));

	$url = $api_url . 'v5/organizations/' . $slug . '/checkout-intents/' . $checkoutIntentId;
	$response = wp_remote_request($url, helloasso_get_args_get_token($helloasso_access_token_asso));

	$response_code = wp_remote_retrieve_response_code($response);
	$body = wp_remote_retrieve_body($response);

	helloasso_log_info('Réponse API HelloAsso pour validation', array(
		'order_id' => $orderId,
		'response_code' => $response_code,
		'response_length' => strlen($body)
	));

	if ($response_code !== 200) {
		helloasso_log_error('Erreur lors de la récupération des détails de commande', array(
			'order_id' => $orderId,
			'response_code' => $response_code,
			'response_body' => $body
		));
		return $order;
	}

	$haOrder = json_decode($body);

	if (!$haOrder || !isset($haOrder->order) || !isset($haOrder->order->payments) || empty($haOrder->order->payments)) {
		helloasso_log_error('Structure de réponse HelloAsso invalide', array(
			'order_id' => $orderId,
			'response_body' => $body
		));
		return $order;
	}

	$payment_state = $haOrder->order->payments[0]->state ?? 'unknown';

	helloasso_log_info('État du paiement HelloAsso', array(
		'order_id' => $orderId,
		'payment_state' => $payment_state
	));

	if ($payment_state == 'Authorized') {
		helloasso_log_info('Paiement autorisé - marquage de la commande comme payée', array('order_id' => $orderId));
		$order->payment_complete();
	} else if ($payment_state == 'Refused') {
		helloasso_log_info('Paiement refusé - marquage de la commande comme échouée', array('order_id' => $orderId));
		$order->update_status('failed');
	} else {
		helloasso_log_warning('État de paiement non géré', array(
			'order_id' => $orderId,
			'payment_state' => $payment_state
		));
	}

	return $order;
}
