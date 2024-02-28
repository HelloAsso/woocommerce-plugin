<?php


/* Return of the HelloAsso API */

add_action('woocommerce_api_helloasso', 'helloasso_endpoint');

function helloasso_endpoint() {

	if (isset($_GET['nonce'])) {
		$nonce = sanitize_text_field($_GET['nonce']);
		if (wp_verify_nonce($nonce, 'helloasso_connect_return')) {
			$nonceRequest = $_GET['nonce'];
		} else {
			wp_safe_redirect(get_site_url());
			exit;
		}
	} else {
		wp_safe_redirect(get_site_url());
		exit;
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

	if (get_option('hello_asso_state') !== $state) {

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

	if (is_wp_error($response)) {
		wp_safe_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=error_connect&nonce=' . $nonce);
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

		$urlOrga = $api_url . 'v5/organizations/' . $data->organization_slug;
		$responseOrga = wp_remote_get($urlOrga, helloasso_get_args_get_token($data->access_token));

		if (is_wp_error($responseOrga)) {
			wp_safe_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=error_connect&nonce=' . $nonce);
			exit;
		}

		$dataOrga = json_decode(wp_remote_retrieve_body($responseOrga));

		if (isset($dataOrga->name)) {
			delete_option('helloasso_organization_name');
			add_option('helloasso_organization_name', $dataOrga->name);
		}

		$urlNotif = $api_url . 'v5/partners/me/api-notifications/organizations/' . $data->organization_slug;

		$dataNotifSend = array(
			'url' => get_site_url() . '/wc-api/helloasso_webhook'
		);

		$responseNotif = wp_remote_request($urlNotif, helloasso_get_args_put_token($dataNotifSend, $data->access_token));

		if (is_wp_error($responseNotif)) {
			return null;
		}

		delete_option('helloasso_webhook_url');
		add_option('helloasso_webhook_url', get_site_url() . '/wc-api/helloasso_webhook');

		wp_safe_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=success_connect&nonce=' . $nonce);
		exit;
	}

	exit;
}


add_action('woocommerce_api_helloasso_deco', 'helloasso_endpoint_deco');

function helloasso_endpoint_deco() {
	delete_option('helloasso_access_token');
	delete_option('helloasso_refresh_token');
	delete_option('helloasso_token_expires_in');
	delete_option('helloasso_refresh_token_expires_in');
	delete_option('helloasso_code_verifier');
	delete_option('hello_asso_state');
	delete_option('hello_asso_authorization_url');
	delete_option('helloasso_organization_slug');
	delete_option('helloasso_access_token_asso');
	delete_option('helloasso_refresh_token_asso');
	delete_option('helloasso_token_expires_in_asso');
	delete_option('helloasso_refresh_token_expires_in_asso');
	delete_option('helloasso_organization_name');
	delete_option('helloasso_webhook_url');
	echo wp_json_encode(array('success' => true, 'message' => 'Vous avez bien été déconnecté de votre compte HelloAsso'));
	exit;
}

add_action('woocommerce_api_helloasso_webhook', 'helloasso_endpoint_webhook');

function helloasso_endpoint_webhook() {
	$data = json_decode(file_get_contents('php://input'), true);
	add_option('helloasso_webhook_data', wp_json_encode($data));

	if ('Payment' === $data['eventType']) {
		$order = wc_get_order($data['metadata']['reference']);
		$order->update_status('processing');
	}

	if ('Organization' === $data['eventType']) {
		delete_option('helloasso_organization_slug');
		add_option('helloasso_organization_slug', $data['data']['new_slug_organization']);

		$helloasso_refresh_token_asso = get_option('helloasso_refresh_token_asso');
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

		if ($helloasso_refresh_token_asso) {
			$url = $api_url . 'oauth2/token';

			$data = array(
				'client_id' => $client_id,
				'client_secret' => $client_secret,
				'grant_type' => 'refresh_token',
				'refresh_token' => $helloasso_refresh_token_asso
			);

			$response = wp_remote_post($url, helloasso_get_args_post($data));

			if (is_wp_error($response)) {
				return null;
			}

			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body);

			if (isset($data->access_token)) {
				update_option('helloasso_access_token_asso', $data->access_token);
				update_option('helloasso_refresh_token_asso', $data->refresh_token);
				update_option('helloasso_token_expires_in_asso', $data->expires_in);
				update_option('helloasso_refresh_token_expires_in_asso', time() + 2629800);
				return $data->access_token;
			} else {
				return null;
			}
		}
	}

	exit;
}

add_action('woocommerce_api_helloasso_order', 'helloasso_endpoint_order');

function helloasso_endpoint_order() {

	if (isset($_GET['nonce'])) {
		$nonce = sanitize_text_field($_GET['nonce']);
		if (!wp_verify_nonce($nonce, 'helloasso_order')) {
			wp_safe_redirect(get_site_url());
			exit;
		}
	} else {
		wp_safe_redirect(get_site_url());
		exit;
	}




	if (isset($_GET['type']) && isset($_GET['order_id'])) {
		$type = sanitize_text_field($_GET['type']);
		$order_id = sanitize_text_field($_GET['order_id']);

		if ('error' === $type) {
			$order = wc_get_order($order_id);
			$order->update_status('failed');
			wp_safe_redirect($order->get_checkout_order_received_url());
		}

		if ('return' === $type) {

			if (isset($_GET['code'])) {
				$code = sanitize_text_field($_GET['code']);

				if ('succeeded' === $code) {
					$order = wc_get_order($order_id);
					$order->update_status('pending');
					wp_safe_redirect($order->get_checkout_order_received_url());
				}

				if ('refused' === $code) {
					$order = wc_get_order($order_id);
					$order->update_status('failed');
					wp_safe_redirect($order->get_checkout_order_received_url());
				}
			}
		}
	}
}
