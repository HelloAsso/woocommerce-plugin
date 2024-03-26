<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; //Exit if accessed directly
}
function helloasso_get_oauth_token($client_id, $client_secret, $api_url) {

	$access_token = get_option('helloasso_access_token');
	$refresh_token = get_option('helloasso_refresh_token');
	$token_expires_in = get_option('helloasso_token_expires_in');
	$refresh_token_expires_in = get_option('helloasso_refresh_token_expires_in');

	if ($access_token && time() < $token_expires_in) {
		return $access_token;
	}

	if ($refresh_token && time() < $refresh_token_expires_in) {
		$url = $api_url . 'oauth2/token';

		$data = array(
			'client_id' => $client_id,
			'client_secret' => $client_secret,
			'grant_type' => 'refresh_token',
			'refresh_token' => $refresh_token
		);

		$response = wp_remote_post($url, helloasso_get_args_post($data));

		if (is_wp_error($response)) {
			return null;
		}

		$response_body = wp_remote_retrieve_body($response);
		$data = json_decode($response_body);

		if (isset($data->access_token)) {
			update_option('helloasso_access_token', $data->access_token);
			update_option('helloasso_refresh_token', $data->refresh_token);
			update_option('helloasso_token_expires_in', $data->expires_in);
			update_option('helloasso_refresh_token_expires_in', time() + 2629800);
			return $data->access_token;
		} else {
			return null;
		}
	}

	if (!$refresh_token) {
		$url = $api_url . '/oauth2/token';

		$data = array(
			'client_id' => $client_id,
			'client_secret' => $client_secret,
			'grant_type' => 'client_credentials'
		);

		$response = wp_remote_post($url, helloasso_get_args_post($data));

		if (is_wp_error($response)) {
			return null;
		}

		$response_body = wp_remote_retrieve_body($response);
		$data = json_decode($response_body);

		if (isset($data->access_token)) {
			add_option('helloasso_access_token', $data->access_token);
			add_option('helloasso_refresh_token', $data->refresh_token);
			add_option('helloasso_token_expires_in', $data->expires_in);
			add_option('helloasso_refresh_token_expires_in', time() + 2629800);
			return $data->access_token;
		} else {
			return null;
		}
	}
}
