<?php
if (!defined('ABSPATH')) {
	exit;
}

function hello_asso_cron_refresh_token()
{
	if (!get_option('helloasso_refresh_token_asso')) {
		return;
	}

	helloasso_refresh_token_asso();
}

function helloasso_is_timestamp($value)
{
	return (int) $value >= 1000000000;
}

function helloasso_asso_access_token_is_valid($access_token, $expires_at)
{
	return !empty($access_token)
		&& helloasso_is_timestamp($expires_at)
		&& time() < ((int) $expires_at - 60);
}

function helloasso_refresh_token_asso()
{
	$access_token = get_option('helloasso_access_token_asso');
	$refresh_token = get_option('helloasso_refresh_token_asso');
	$token_expires_in = (int) get_option('helloasso_token_expires_in_asso');

	if (!$refresh_token) {
		helloasso_log_error('Refresh token association manquant, reconnexion requise');
		return null;
	}

	if (helloasso_asso_access_token_is_valid($access_token, $token_expires_in)) {
		helloasso_log_debug('Access token association encore valide', array(
			'expires_in' => $token_expires_in - time()
		));
		return $access_token;
	}

	$lock_key = 'helloasso_token_refresh_lock';
	$got_lock = add_option($lock_key, time(), '', 'no');
	if (!$got_lock) {
		$lock_age = time() - (int) get_option($lock_key);
		if ($lock_age > 20) {
			delete_option($lock_key);
			$got_lock = add_option($lock_key, time(), '', 'no');
		}
	}

	if (!$got_lock) {
		for ($i = 0; $i < 5; $i++) {
			usleep(200000);
			wp_cache_delete('alloptions', 'options');
			$access_token = get_option('helloasso_access_token_asso');
			$token_expires_in = (int) get_option('helloasso_token_expires_in_asso');
			if (helloasso_asso_access_token_is_valid($access_token, $token_expires_in)) {
				return $access_token;
			}
		}
		helloasso_log_warning('Refresh du token association déjà en cours');
		return get_option('helloasso_access_token_asso') ?: null;
	}

	try {
		wp_cache_delete('alloptions', 'options');
		$access_token = get_option('helloasso_access_token_asso');
		$refresh_token = get_option('helloasso_refresh_token_asso');
		$token_expires_in = (int) get_option('helloasso_token_expires_in_asso');

		if (helloasso_asso_access_token_is_valid($access_token, $token_expires_in)) {
			return $access_token;
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

		helloasso_log_info('Rafraîchissement du token association', array(
			'test_mode' => $isInTestMode
		));

		$response = wp_remote_post($api_url . 'oauth2/token', helloasso_get_args_post_urlencode(array(
			'client_id' => $client_id,
			'client_secret' => $client_secret,
			'grant_type' => 'refresh_token',
			'refresh_token' => $refresh_token
		)));

		if (is_wp_error($response)) {
			helloasso_log_error('Erreur réseau lors du rafraîchissement du token association', array(
				'error' => $response->get_error_message(),
				'error_code' => $response->get_error_code()
			));
			return null;
		}

		$response_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);
		$token_data = json_decode($response_body);

		if ($response_code !== 200 || !isset($token_data->access_token)) {
			helloasso_log_error('Échec du rafraîchissement du token association', array(
				'response_code' => $response_code,
				'response_body' => $response_body
			));
			return null;
		}

		update_option('helloasso_access_token_asso', $token_data->access_token);
		update_option('helloasso_refresh_token_asso', $token_data->refresh_token);
		update_option('helloasso_token_expires_in_asso', time() + $token_data->expires_in);
		update_option('helloasso_refresh_token_expires_in_asso', time() + HELLOASSO_REFRESH_TOKEN_LIFETIME);

		helloasso_log_info('Token association rafraîchi avec succès', array(
			'expires_in' => $token_data->expires_in
		));

		return $token_data->access_token;
	} finally {
		delete_option($lock_key);
	}
}

function helloasso_unschedule_legacy_wp_cron()
{
	$timestamp = wp_next_scheduled('hello_asso_cron_refresh_token_hook');
	if ($timestamp) {
		wp_unschedule_event($timestamp, 'hello_asso_cron_refresh_token_hook');
	}
	wp_clear_scheduled_hook('hello_asso_cron_refresh_token_hook');
}

function helloasso_schedule_refresh_token()
{
	if (function_exists('as_has_scheduled_action') && function_exists('as_schedule_recurring_action')) {
		helloasso_unschedule_legacy_wp_cron();

		if (!as_has_scheduled_action('helloasso_refresh_token_action', array(), 'helloasso')) {
			as_schedule_recurring_action(time() + 60, HOUR_IN_SECONDS, 'helloasso_refresh_token_action', array(), 'helloasso');
		}
		return;
	}

	if (!wp_next_scheduled('hello_asso_cron_refresh_token_hook')) {
		wp_schedule_event(time(), 'hourly', 'hello_asso_cron_refresh_token_hook');
	}
}

add_action('init', 'helloasso_schedule_refresh_token');
add_action('helloasso_refresh_token_action', 'hello_asso_cron_refresh_token');
add_action('hello_asso_cron_refresh_token_hook', 'hello_asso_cron_refresh_token');
