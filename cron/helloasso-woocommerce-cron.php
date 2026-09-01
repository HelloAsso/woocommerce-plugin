<?php

// Durée de validité du refresh token : 29 jours en secondes (durée réelle confirmée côté HelloAsso)
if (!defined('HELLOASSO_REFRESH_TOKEN_LIFETIME')) {
	define('HELLOASSO_REFRESH_TOKEN_LIFETIME', 29 * 24 * 60 * 60); // 2505600 secondes
}

function hello_asso_cron_refresh_token()
{
	helloasso_refresh_token_asso();
}

if (!wp_next_scheduled('hello_asso_cron_refresh_token_hook')) {
	wp_schedule_event(strtotime('00:00:00'), 'daily', 'hello_asso_cron_refresh_token_hook');
}

// Marge de sécurité (en secondes) avant expiration pendant laquelle on considère
// qu'il faut rafraîchir le token par anticipation.
define('HELLOASSO_TOKEN_REFRESH_BUFFER', 60);

// Durée maximale de détention du verrou de rafraîchissement avant de le considérer périmé
// (protection contre un verrou orphelin suite à un crash PHP).
define('HELLOASSO_REFRESH_LOCK_TTL', 10);

/**
 * Rafraîchit (si nécessaire) le token d'accès HelloAsso de l'association.
 *
 * Le refresh_token HelloAsso est à usage unique (rotation OAuth2) : si plusieurs
 * requêtes concurrentes (ex. plusieurs paiements simultanés) l'utilisent en même
 * temps, seule la première réussit et les suivantes échouent silencieusement,
 * laissant l'application avec un access_token invalidé. Pour éviter cela :
 *  - on ne rafraîchit que si le token courant est expiré (ou proche de l'être) ;
 *  - on protège l'appel de rafraîchissement par un verrou (option WP utilisée
 *    comme mutex) afin qu'une seule requête effectue l'appel réseau, les autres
 *    réutilisant le token fraîchement mis à jour.
 *
 * @param bool $force Force le rafraîchissement même si le token courant est encore valide.
 * @return string|null Le token d'accès valide, ou null en cas d'échec.
 */
function helloasso_refresh_token_asso($force = false)
{
	$access_token = get_option('helloasso_access_token_asso');
	$token_expires_in = get_option('helloasso_token_expires_in_asso');

	if (!$force && $access_token && $token_expires_in && time() < ((int) $token_expires_in - HELLOASSO_TOKEN_REFRESH_BUFFER)) {
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

	// Acquisition du verrou : add_option() échoue si l'option existe déjà,
	// ce qui nous sert de mutex best-effort entre requêtes concurrentes.
	$lock_acquired = false;
	for ($attempt = 0; $attempt < 20; $attempt++) {
		if (add_option('helloasso_refresh_lock_asso', time(), '', 'no')) {
			$lock_acquired = true;
			break;
		}

		$lock_time = (int) get_option('helloasso_refresh_lock_asso');
		if ($lock_time && (time() - $lock_time) > HELLOASSO_REFRESH_LOCK_TTL) {
			// Verrou périmé (probablement un crash pendant le rafraîchissement) : on le reprend.
			update_option('helloasso_refresh_lock_asso', time());
			$lock_acquired = true;
			break;
		}

		usleep(150000); // 150ms

		// Un autre process a peut-être déjà rafraîchi le token pendant l'attente.
		$access_token = get_option('helloasso_access_token_asso');
		$token_expires_in = get_option('helloasso_token_expires_in_asso');
		if (!$force && $access_token && $token_expires_in && time() < ((int) $token_expires_in - HELLOASSO_TOKEN_REFRESH_BUFFER)) {
			return $access_token;
		}
	}

	if (!$lock_acquired) {
		if (function_exists('helloasso_log_error')) {
			helloasso_log_error('Impossible d\'acquérir le verrou de rafraîchissement du token HelloAsso', array());
		}
		return $access_token ?: null;
	}

	try {
		$helloasso_refresh_token_asso = get_option('helloasso_refresh_token_asso');

		if (!$helloasso_refresh_token_asso) {
			return $access_token ?: null;
		}

		$url = $api_url . 'oauth2/token';

		$data = array(
			'client_id' => $client_id,
			'client_secret' => $client_secret,
			'grant_type' => 'refresh_token',
			'refresh_token' => $helloasso_refresh_token_asso
		);

		$response = wp_remote_post($url, helloasso_get_args_post_urlencode($data));

		if (is_wp_error($response)) {
			if (function_exists('helloasso_log_error')) {
				helloasso_log_error('Erreur réseau lors du rafraîchissement du token HelloAsso', array(
					'error' => $response->get_error_message()
				));
			}
			return null;
		}

		$response_body = wp_remote_retrieve_body($response);
		$response_code = wp_remote_retrieve_response_code($response);
		$data = json_decode($response_body);

		if (isset($data->access_token)) {
			update_option('helloasso_access_token_asso', $data->access_token);
			update_option('helloasso_refresh_token_asso', $data->refresh_token);
			update_option('helloasso_token_expires_in_asso', time() + $data->expires_in);
			update_option('helloasso_refresh_token_expires_in_asso', time() + HELLOASSO_REFRESH_TOKEN_LIFETIME);
			delete_option('helloasso_connection_lost_asso');
			return $data->access_token;
		}

		if (function_exists('helloasso_log_error')) {
			helloasso_log_error('Reponse invalide lors du rafraichissement du token HelloAsso', array(
				'response_code' => $response_code,
				'response_body' => $response_body
			));
		}

		if (in_array((int) $response_code, array(400, 401), true)) {
			update_option('helloasso_connection_lost_asso', array(
				'time' => time(),
				'response_code' => $response_code,
				'response_body' => $response_body
			));
		}

		return null;
	} finally {
		delete_option('helloasso_refresh_lock_asso');
	}
}

add_action('admin_notices', 'helloasso_connection_lost_admin_notice');
function helloasso_connection_lost_admin_notice()
{
	if (!current_user_can('manage_woocommerce')) {
		return;
	}

	$connection_lost = get_option('helloasso_connection_lost_asso');
	if (!$connection_lost) {
		return;
	}

	$settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=helloasso');
	printf(
		'<div class="notice notice-error"><p><strong>HelloAsso :</strong> %s <a href="%s">%s</a></p></div>',
		esc_html__('la connexion a votre compte HelloAsso a expire, les paiements de vos clients ne peuvent plus aboutir.', 'woocommerce-helloasso'),
		esc_url($settings_url),
		esc_html__('Reconnectez le plugin depuis les reglages de paiement', 'woocommerce-helloasso')
	);
}

add_action('hello_asso_cron_refresh_token_hook', 'hello_asso_cron_refresh_token');
