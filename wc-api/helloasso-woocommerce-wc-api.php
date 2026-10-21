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

	// Réponse 200 inexploitable : on renvoie vers les réglages avec une erreur plutôt qu'une page blanche
	if (!is_object($data) || empty($data->access_token) || !is_string($data->access_token)) {
		helloasso_log_error('Réponse OAuth2 invalide : access_token absent', array(
			'response_body_length' => strlen($response_body)
		));
		wp_safe_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=error_connect&nonce=' . $nonce);
		exit;
	}

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
		delete_option('helloasso_pending_organization_slug');
		add_option('helloasso_organization_slug', $data->organization_slug);
		add_option('helloasso_access_token_asso', $data->access_token);
		add_option('helloasso_refresh_token_asso', $data->refresh_token);
		add_option('helloasso_token_expires_in_asso', time() + $data->expires_in);
		add_option('helloasso_refresh_token_expires_in_asso', time() + HELLOASSO_REFRESH_TOKEN_LIFETIME);

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
			delete_option('helloasso_pending_organization_slug');
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
	// Réservé aux gestionnaires de la boutique, via le lien de l'admin (nonce créé dans admin_options())
	if (!current_user_can('manage_woocommerce') || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'helloasso_deco')) {
		helloasso_log_warning('Déconnexion HelloAsso refusée : droits ou nonce invalides', array(
			'user_id' => get_current_user_id()
		));
		echo wp_json_encode(array('success' => false, 'message' => 'La déconnexion de HelloAsso a échoué. Rechargez la page et réessayez.'));
		exit;
	}

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
	delete_option('helloasso_pending_organization_slug');
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

	if (!is_array($data)) {
		helloasso_log_error('Payload webhook invalide', array(
			'raw_data_length' => strlen($raw_input)
		));
		exit;
	}

	$event_type = isset($data['eventType']) && is_string($data['eventType']) ? $data['eventType'] : 'unknown';

	helloasso_log_info('Webhook HelloAsso reçu', array(
		'event_type' => $event_type,
		'raw_data_length' => strlen($raw_input)
	));

	add_option('helloasso_webhook_data', wp_json_encode($data));

	if ('Order' === $event_type) {
		// La référence (id de la commande) et le checkoutIntentId arrivent en entiers : on accepte tout scalaire
		$reference = isset($data['metadata']['reference']) && is_scalar($data['metadata']['reference']) ? (string) $data['metadata']['reference'] : '';
		$checkout_intent_id = isset($data['data']['checkoutIntentId']) && is_scalar($data['data']['checkoutIntentId']) ? (string) $data['data']['checkoutIntentId'] : '';

		helloasso_log_info('Traitement d\'un événement Order', array(
			'order_reference' => '' !== $reference ? $reference : 'unknown',
			'checkout_intent_id' => '' !== $checkout_intent_id ? $checkout_intent_id : 'unknown'
		));

		if ('' === $reference || '' === $checkout_intent_id) {
			helloasso_log_error('Webhook Order incomplet : référence ou checkoutIntentId manquant');
			exit;
		}

		validate_order($reference, $checkout_intent_id);
	} else if ('Organization' === $event_type) {
		$new_slug = isset($data['data']['new_slug_organization']) && is_string($data['data']['new_slug_organization']) ? $data['data']['new_slug_organization'] : '';

		helloasso_log_info('Traitement d\'un événement Organization', array(
			'new_slug' => '' !== $new_slug ? $new_slug : 'unknown'
		));

		// Sans nouveau slug, on garde l'actuel plutôt que de l'effacer
		if ('' === $new_slug) {
			helloasso_log_error('Webhook Organization incomplet : nouveau slug manquant');
			exit;
		}

		// Le slug finit dans les URL de l'API : lettres, chiffres, tirets et underscores uniquement
		if (!preg_match('/^[a-z0-9_-]+$/i', $new_slug)) {
			helloasso_log_error('Webhook Organization ignoré : slug invalide', array('new_slug' => $new_slug));
			exit;
		}

		if (get_option('helloasso_organization_slug') === $new_slug) {
			helloasso_log_info('Webhook Organization : slug déjà à jour', array('new_slug' => $new_slug));
			exit;
		}

		// Le webhook n'est pas authentifié : le nouveau slug n'est enregistré qu'une fois confirmé par l'API
		$confirmation = helloasso_confirm_organization_slug($new_slug);

		if ('retry' === $confirmation) {
			// Échec passager (réseau, 5xx) : le cron horaire retentera la confirmation
			update_option('helloasso_pending_organization_slug', array('slug' => $new_slug, 'since' => time()), false);
			helloasso_log_warning('Webhook Organization : confirmation du slug reportée', array('new_slug' => $new_slug));
			exit;
		}

		if ('confirmed' !== $confirmation) {
			helloasso_log_error('Webhook Organization ignoré : nouveau slug refusé par HelloAsso', array('new_slug' => $new_slug));
			exit;
		}

		helloasso_apply_organization_slug($new_slug);
	} else {
		helloasso_log_warning('Événement webhook non reconnu', array(
			'event_type' => $event_type
		));
	}

	exit;
}

function helloasso_apply_organization_slug($slug)
{
	delete_option('helloasso_organization_slug');
	add_option('helloasso_organization_slug', $slug);
	delete_option('helloasso_pending_organization_slug');

	helloasso_log_info('Slug de l\'association mis à jour', array('new_slug' => $slug));
}

// Appelé par le cron horaire : retente la confirmation d'un slug reporté après un échec passager
function helloasso_retry_pending_organization_slug()
{
	$pending = get_option('helloasso_pending_organization_slug');
	if (!is_array($pending) || empty($pending['slug']) || !is_string($pending['slug'])) {
		return;
	}

	// Au-delà de 7 jours on abandonne : une reconnexion du plugin enregistre de toute façon le bon slug
	$since = isset($pending['since']) ? (int) $pending['since'] : 0;
	if (time() - $since > 7 * DAY_IN_SECONDS) {
		delete_option('helloasso_pending_organization_slug');
		helloasso_log_error('Confirmation du slug abandonnée après 7 jours', array('new_slug' => $pending['slug']));
		return;
	}

	$confirmation = helloasso_confirm_organization_slug($pending['slug']);

	if ('confirmed' === $confirmation) {
		helloasso_apply_organization_slug($pending['slug']);
	} elseif ('refused' === $confirmation) {
		delete_option('helloasso_pending_organization_slug');
		helloasso_log_error('Slug en attente refusé par HelloAsso', array('new_slug' => $pending['slug']));
	}
}

// Confirme qu'un slug désigne l'association liée : réenregistrer l'URL de notification sur ce slug
// (même appel qu'à la connexion) demande le rôle OrganizationAdmin, que seul le token de cette association possède.
// Renvoie 'confirmed', 'refused' (refus définitif de l'API) ou 'retry' (échec passager : réseau, 5xx, 429, token indisponible).
function helloasso_confirm_organization_slug($slug)
{
	$access_token = helloasso_refresh_token_asso();
	if (!$access_token) {
		helloasso_log_error('Impossible de confirmer le slug : token association manquant');
		return 'retry';
	}

	if ('yes' === get_option('helloasso_testmode')) {
		$api_url = HELLOASSO_WOOCOMMERCE_API_URL_TEST;
	} else {
		$api_url = HELLOASSO_WOOCOMMERCE_API_URL_PROD;
	}

	$dataNotifSend = array(
		'url' => get_site_url() . '/wc-api/helloasso_webhook'
	);

	$response = wp_remote_request($api_url . 'v5/partners/me/api-notifications/organizations/' . $slug, helloasso_get_args_put_token($dataNotifSend, $access_token));

	if (is_wp_error($response)) {
		helloasso_log_error('Erreur réseau lors de la confirmation du slug', array(
			'error' => $response->get_error_message()
		));
		return 'retry';
	}

	$status_code = (int) wp_remote_retrieve_response_code($response);
	if (200 === $status_code) {
		return 'confirmed';
	}

	if ($status_code >= 500 || 429 === $status_code || 408 === $status_code) {
		helloasso_log_error('Erreur temporaire de l\'API HelloAsso lors de la confirmation du slug', array(
			'new_slug' => $slug,
			'status_code' => $status_code
		));
		return 'retry';
	}

	helloasso_log_error('Slug refusé par l\'API HelloAsso', array(
		'new_slug' => $slug,
		'status_code' => $status_code
	));
	return 'refused';
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

	// L'id finit dans l'URL de l'API : pas de / ni de caractères spéciaux
	if (!preg_match('/^[A-Za-z0-9_-]+$/', (string) $checkoutIntentId)) {
		helloasso_log_error('checkoutIntentId invalide lors de la validation', array(
			'order_id' => $orderId,
			'checkout_intent_id' => $checkoutIntentId
		));
		return $order;
	}

	$isInTestMode = get_option('helloasso_testmode');
	if ('yes' === $isInTestMode) {
		$api_url = HELLOASSO_WOOCOMMERCE_API_URL_TEST;
	} else {
		$api_url = HELLOASSO_WOOCOMMERCE_API_URL_PROD;
	}

	$helloasso_access_token_asso = helloasso_refresh_token_asso();
	$slug = get_option('helloasso_organization_slug');

	helloasso_log_info('Récupération des détails de la commande HelloAsso', array(
		'order_id' => $orderId,
		'organization_slug' => $slug,
		'api_url' => $api_url,
		'has_token' => !empty($helloasso_access_token_asso)
	));

	if (!$helloasso_access_token_asso) {
		helloasso_log_error('Impossible de valider la commande : token association manquant', array(
			'order_id' => $orderId
		));
		return $order;
	}

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

	// Le checkout intent doit avoir été créé pour cette commande (metadata.reference envoyée par process_payment()).
	// On ne bloque que si la référence est présente et désigne une autre commande : absente, on valide comme avant.
	$checkout_reference = isset($haOrder->metadata->reference) && is_scalar($haOrder->metadata->reference) ? (string) $haOrder->metadata->reference : '';

	if ('' === $checkout_reference) {
		helloasso_log_warning('Référence absente du checkout intent, correspondance avec la commande non vérifiée', array(
			'order_id' => $orderId,
			'checkout_intent_id' => $checkoutIntentId
		));
	} elseif ($checkout_reference !== (string) $order->get_id()) {
		helloasso_log_error('Paiement HelloAsso créé pour une autre commande, validation ignorée', array(
			'order_id' => $orderId,
			'checkout_intent_id' => $checkoutIntentId,
			'checkout_reference' => $checkout_reference
		));
		return $order;
	}

	$payment_state = $haOrder->order->payments[0]->state ?? 'unknown';
	$paymentReceiptUrl = $haOrder->order->payments[0]->paymentReceiptUrl ?? false;
	helloasso_log_info('État du paiement HelloAsso', array(
		'order_id' => $orderId,
		'payment_state' => $payment_state,
		'paymentReceiptUrl' => $paymentReceiptUrl
	));

	if ($payment_state == 'Authorized') {
		helloasso_log_info('Paiement autorisé - marquage de la commande comme payée', array('order_id' => $orderId));
		$order->payment_complete();
		if($paymentReceiptUrl){
			$order->add_order_note('Paiement HelloAsso autorisé.<br>  <a  target="_blank" href="' . $paymentReceiptUrl . '">Attestation de paiement</a>');
		}
		else{
		$order->add_order_note('Paiement HelloAsso autorisé.');

		}
	} else if ($payment_state == 'Refused') {
		helloasso_log_info('Paiement refusé - marquage de la commande comme échouée', array('order_id' => $orderId));
		$order->update_status('failed');
		$order->add_order_note('Paiement HelloAsso refusé.');
	} else {
		helloasso_log_warning('État de paiement non géré', array(
			'order_id' => $orderId,
			'payment_state' => $payment_state
		));
	}

	return $order;
}

