<?php
if (! defined('ABSPATH')) {
	exit; //Exit if accessed directly
}

function helloasso_get_oauth_token($client_id, $client_secret, $api_url)
{
	helloasso_log_debug('Vérification du token OAuth2', array(
		'api_url' => $api_url,
		'client_id' => substr($client_id, 0, 10) . '...'
	));

	$access_token = get_option('helloasso_access_token');
	$refresh_token = get_option('helloasso_refresh_token');
	$token_expires_in = get_option('helloasso_token_expires_in');
	$refresh_token_expires_in = get_option('helloasso_refresh_token_expires_in');

	if ($access_token && time() < $token_expires_in) {
		helloasso_log_debug('Token OAuth2 encore valide', array(
			'token_preview' => substr($access_token, 0, 10) . '...',
			'expires_in' => $token_expires_in - time()
		));
		return $access_token;
	}

	if ($refresh_token && time() < $refresh_token_expires_in) {
		helloasso_log_info('Rafraîchissement du token OAuth2', array(
			'refresh_token_preview' => substr($refresh_token, 0, 10) . '...'
		));

		$url = $api_url . 'oauth2/token';

		$data = array(
			'client_id' => $client_id,
			'client_secret' => $client_secret,
			'grant_type' => 'refresh_token',
			'refresh_token' => $refresh_token
		);

		$response = wp_remote_post($url, helloasso_get_args_post_urlencode($data));

		if (is_wp_error($response)) {
			helloasso_log_error('Erreur lors du rafraîchissement du token OAuth2', array(
				'error' => $response->get_error_message(),
				'error_code' => $response->get_error_code()
			));
			return null;
		}

		$response_body = wp_remote_retrieve_body($response);
		$response_code = wp_remote_retrieve_response_code($response);
		$data = json_decode($response_body);

		helloasso_log_info('Réponse rafraîchissement token OAuth2', array(
			'response_code' => $response_code,
			'has_access_token' => isset($data->access_token)
		));

		if (isset($data->access_token)) {
			update_option('helloasso_access_token', $data->access_token);
			update_option('helloasso_refresh_token', $data->refresh_token);
			update_option('helloasso_token_expires_in', $data->expires_in);
			update_option('helloasso_refresh_token_expires_in', time() + 2629800);

			helloasso_log_info('Token OAuth2 rafraîchi avec succès', array(
				'new_token_preview' => substr($data->access_token, 0, 10) . '...',
				'expires_in' => $data->expires_in
			));

			return $data->access_token;
		} else {
			helloasso_log_error('Réponse invalide lors du rafraîchissement du token', array(
				'response_code' => $response_code,
				'response_body' => $response_body
			));
			return null;
		}
	}

	if (!$refresh_token) {
		helloasso_log_info('Demande de nouveau token OAuth2 avec client_credentials', array(
			'api_url' => $api_url
		));

		$url = $api_url . 'oauth2/token';

		$data = array(
			'client_id' => $client_id,
			'client_secret' => $client_secret,
			'grant_type' => 'client_credentials'
		);

		$response = wp_remote_post($url, helloasso_get_args_post_urlencode($data));

		if (is_wp_error($response)) {
			helloasso_log_error('Erreur lors de la demande de token OAuth2', array(
				'error' => $response->get_error_message(),
				'error_code' => $response->get_error_code()
			));
			return null;
		}

		$response_body = wp_remote_retrieve_body($response);
		$response_code = wp_remote_retrieve_response_code($response);
		$data = json_decode($response_body);

		helloasso_log_info('Réponse demande token OAuth2', array(
			'response_code' => $response_code,
			'has_access_token' => isset($data->access_token)
		));

		if (isset($data->access_token)) {
			add_option('helloasso_access_token', $data->access_token);
			add_option('helloasso_refresh_token', $data->refresh_token);
			add_option('helloasso_token_expires_in', $data->expires_in);
			add_option('helloasso_refresh_token_expires_in', time() + 2629800);

			helloasso_log_info('Nouveau token OAuth2 obtenu avec succès', array(
				'token_preview' => substr($data->access_token, 0, 10) . '...',
				'expires_in' => $data->expires_in
			));

			return $data->access_token;
		} else {
			helloasso_log_error('Réponse invalide lors de la demande de token', array(
				'response_code' => $response_code,
				'response_body' => $response_body
			));
			return null;
		}
	}

	helloasso_log_warning('Aucun token OAuth2 disponible et aucun refresh token valide');
	return null;
}
