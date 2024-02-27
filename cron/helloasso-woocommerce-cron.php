<?php

function cron_refresh_token_hello_asso() {
	refresh_token_asso();
}

if (!wp_next_scheduled('cron_refresh_token_hello_asso__hook')) {
	wp_schedule_event(strtotime('00:00:00'), 'daily', 'cron_refresh_token_hello_asso__hook');
}

function refresh_token_asso() {
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

		$response = wp_remote_post($url, helloasso_get_args_post_urlencode($data));

		if (is_wp_error($response)) {
			return null;
		}

		$response_body = wp_remote_retrieve_body($response);
		$data = json_decode($response_body);

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

add_action('cron_refresh_token_hello_asso__hook', 'cron_refresh_token_hello_asso');
