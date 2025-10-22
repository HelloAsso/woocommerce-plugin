<?php

/**
 * Plugin Name:       HelloAsso Payments for WooCommerce
 * Description:       Recevez 100% de vos paiements gratuitement. HelloAsso est la seule solution de paiement gratuite du secteur associatif. Nous sommes financés librement par la solidarité de celles et ceux qui choisissent de laisser une contribution volontaire au moment du paiement à une association.
 * Version:           1.0.11
 * Requires at least: 5.0
 * WC requires at least: 7.7
 * Requires PHP:      7.2.34
 * Author:            HelloAsso
 * Author URI:        https://helloasso.com
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */


if (!defined('ABSPATH')) {
	exit; //Exit if accessed directly
}

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

require_once('helper/helloasso-woocommerce-api-call.php');
require_once('helper/helloasso-woocommerce-config.php');
require_once('helper/helloasso-woocommerce-helper.php');
require_once('cron/helloasso-woocommerce-cron.php');
require_once('helloasso-api/helloasso-woocommerce-api.php');
require_once('wc-api/helloasso-woocommerce-wc-api.php');


function helloasso_declare_cart_checkout_blocks_compatibility()
{
	if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
		FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
	}
}

add_action('before_woocommerce_init', 'helloasso_declare_cart_checkout_blocks_compatibility');
add_action('woocommerce_blocks_loaded', 'helloasso_register_order_approval_payment_method_type');

function helloasso_register_order_approval_payment_method_type()
{
	if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
		return;
	}

	require_once plugin_dir_path(__FILE__) . 'block/helloasso-woocommerce-blocks.php';

	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function (PaymentMethodRegistry $payment_method_registry) {
			// Register an instance of Helloasso_Blocks
			$payment_method_registry->register(new Helloasso_Blocks());
		}
	);
}

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'helloasso_add_gateway_class');
function helloasso_add_gateway_class($gateways)
{
	$gateways[] = 'WC_HelloAsso_Gateway';
	return $gateways;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'helloasso_woocommerce_actions_links');
function helloasso_woocommerce_actions_links($links)
{
	$links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=helloasso') . '">' . __('Réglages', 'woocommerce-helloasso') . '</a>';
	return $links;
}

register_activation_hook(__FILE__, 'helloasso_activate');
function helloasso_activate()
{
	if (!class_exists('WooCommerce')) {
		deactivate_plugins(plugin_basename(__FILE__));
		wp_die('Ce plugin nécessite WooCommerce pour fonctionner');
	}

	$currency = get_woocommerce_currency();
	if ($currency !== 'EUR') {
		deactivate_plugins(plugin_basename(__FILE__));
		wp_die('HelloAsso ne prend en charge que les paiements en euros. Veuillez changer la devise de votre boutique en euros pour activer ce plugin.');
	}
}

add_action('plugins_loaded', 'helloasso_init_gateway_class');

register_deactivation_hook(__FILE__, 'helloasso_deactivate');
function helloasso_deactivate()
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
	delete_option('helloasso_testmode');
	delete_option('woocommerce_helloasso_settings');
	delete_option('helloasso_webhook_data');
}

add_action('wp_ajax_helloasso_deco', 'helloasso_deco');

function helloasso_init_gateway_class()
{
	class WC_HelloAsso_Gateway extends WC_Payment_Gateway
	{
		public function __construct()
		{
			helloasso_log_info('Initialisation du gateway HelloAsso', array(
				'plugin_version' => '1.0.11',
				'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'unknown'
			));

			$this->id = 'helloasso';
			$this->icon = null;
			$this->has_fields = true;
			$this->method_title = 'Payer par carte bancaire avec HelloAsso';
			$this->method_description = 'Acceptez des paiements gratuitement avec HelloAsso (0 frais, 0 commission pour votre association).';

			$this->supports = array(
				'products'
			);

			$this->init_form_fields();
			$this->init_settings();

			$this->title = $this->get_option('title');
			$this->description = 'Le modèle solidaire de HelloAsso garantit que 100% de votre paiement sera versé à l’association choisie. Vous pouvez soutenir l’aide qu’ils apportent aux associations en laissant une contribution volontaire à HelloAsso au moment de votre paiement.';
			$this->enabled = $this->get_option('enabled');
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		}

		public function payment_fields()
		{
			if ($this->description) {
				echo '<div style="display: flex; align-items: center;">';
				echo '<img style="max-width: 50px; height:auto; margin-right: 16px;" src="/wp-content/plugins/helloasso-payments-for-woocommerce/assets/logo-ha.png" alt="HelloAsso Logo" />';
				echo '<p>' . wp_kses_post($this->description) . '</p>';
				echo '</div>';
			}

			$multi_3_enabled = $this->get_option('multi_3_enabled') === 'yes';
			$multi_12_enabled = $this->get_option('multi_12_enabled') === 'yes';

			$current_day = (int) current_time('j');
			$can_show_multi_payment = $current_day <= 28;

			if (($multi_3_enabled || $multi_12_enabled) && $can_show_multi_payment) {
				echo '<div id="helloasso-payment-options">';
				echo '<p><strong>Choisissez votre mode de paiement:</strong></p>';

				echo '<label style="display: block; margin-bottom: 8px;">';
				echo '<input type="radio" name="helloasso_payment_type" value="one_time" checked="checked" style="margin-right: 5px;" />';
				echo 'Paiement comptant';
				echo '</label>';

				if ($multi_3_enabled) {
					echo '<label style="display: block; margin-bottom: 8px;">';
					echo '<input type="radio" name="helloasso_payment_type" value="three_times" style="margin-right: 5px;" />';
					echo 'Paiement en 3 fois sans frais';
					echo '</label>';
				}

				if ($multi_12_enabled) {
					echo '<label style="display: block; margin-bottom: 8px;">';
					echo '<input type="radio" name="helloasso_payment_type" value="twelve_times" style="margin-right: 5px;" />';
					echo 'Paiement en 12 fois sans frais';
					echo '</label>';
				}

				echo '</div>';
			}
		}

		public function admin_options()
		{
			// Check if we have helloasso_access_token_asso in the options
			$isConnected = false;
			if (get_option('helloasso_access_token_asso')) {
				$isConnected = true;
			}

			if (isset($_GET['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'helloasso_connect')) {

				if (isset($_GET['msg'])) {
					$msg = sanitize_text_field($_GET['msg']);

					if (isset($msg) && 'error_connect' === $msg) {
						if (isset($_GET['status_code']) && '403' === $_GET['status_code']) {
							echo '<div class="notice notice-error is-dismissible">
			<p>Erreur lors de la connexion à HelloAsso. Veuillez <a href="https://www.helloasso.com/contactez-nous" target="_blank">nous contacter</a>. (Erreur 403)</p>
			</div>';
						} else {
							echo '<div class="notice notice-error is-dismissible">
			<p>Erreur lors de la connexion à HelloAsso. Veuillez réessayer.</p>
			</div>';
						}
					}

					if (isset($msg) && 'success_connect' === $msg) {
						echo '<div class="notice notice-success is-dismissible">
			<p>Connexion à HelloAsso réussie.</p>
			</div>';
					}
				}
			}

			echo '<h3>' . esc_html($this->method_title) . '</h3>';

			echo '
            <p>
            Intégrer HelloAsso, c’est rejoindre un système solidaire, <b>financé par les contributions volontaires des utilisateurs</b>, afin d’offrir des services en ligne, de qualité et gratuits à toutes les associations de France.<br/>
            Ainsi, en le rejoignant, vous bénéficiez de ce modèle solidaire, vous permettant de collecter sans frais des paiements en ligne.<br.>
            Il est donc important de communiquer sur ce modèle, pour informer vos utilisateurs de la portée de leurs paiements.
            <p>
            <p>
            	<strong>Pour accepter les paiements avec HelloAsso, vous devrez vous connecter à votre compte HelloAsso.</strong>
            	<strong>Vous n\'avez pas de compte sur HelloAsso, <a href="https://auth.helloasso.com/inscription?from=woocommerce" target="_blank">créer votre compte en quelques minutes ici.</a></strong>
            </p>
            <p>
            <i>Si vous rencontrez des problèmes, n’hésitez pas à aller faire un tour sur <a href="https://centredaide.helloasso.com/s/" target="_blank">
            notre centre d’aide</a> ou à <a href="https://www.helloasso.com/contactez-nous" target="_blank">nous contacter</a> directement.</i>
            <p>
            <style>
            .HaAuthorizeButton {
            align-items: center;
            -webkit-box-pack: center;
            cursor: pointer;
            -ms-flex-pack: center;
            background-color: #FFFFFF !important;
            border: 0.0625rem solid #49D38A !important;
            border-radius: 0.125rem;
            display: -webkit-box;
            display: -ms-flexbox;
            display: flex !important;
            padding: 0 !important;
            line-height: initial !important;
            }
            .HaAuthorizeButton:disabled {
            background-color: #E9E9F0;
            border-color: transparent;
            cursor: not-allowed;
            }
            .HaAuthorizeButton:not(:disabled):focus {
            box-shadow: 0 0 0 0.25rem rgba(73, 211, 138, 0.25);
            -webkit-box-shadow: 0 0 0 0.25rem rgba(73, 211, 138, 0.25);
            }
            .HaAuthorizeButtonLogo {
            padding: 0 0.8rem;
            width: 2.25rem;
            }
            .HaAuthorizeButtonTitle {
            background-color: #49D38A;
            color: #FFFFFF;
            font-size: 1rem;
            font-weight: 700;
            padding: 0.78125rem 1.5rem;
            }
            .HaAuthorizeButton:disabled .HaAuthorizeButtonTitle {
            background-color: #E9E9F0;
            color: #9A9DA8;
            }
            .HaAuthorizeButton:not(:disabled):hover .HaAuthorizeButtonTitle,
            .HaAuthorizeButton:not(:disabled):focus .HaAuthorizeButtonTitle {
            background-color: #30c677;
            }
            </style>
            ';

			echo '<table class="form-table helloasso">';
			$this->generate_settings_html();
			// change submit button text

			echo '</table>';

			if ($isConnected) {
				$btnText = 'Enregistrer les modifications';
			} else {
				$btnText = 'Enregistrer et se connecter à HelloAsso';
			}

			if ($this->get_option('testmode') === 'yes') {
				echo '<div id="testMode"><p>
            Le mode test vous connectera avec l’environnement de test de HelloAsso (https://www.helloasso-sandbox.com).<br/> Vous pouvez y créer un compte pour tester la connexion.
            
            </p>
            <p><i>
            Pour réaliser des paiements, vous pouvez utiliser les cartes de test suivantes : 4242 4242 4242 4242 ou 5017 6791 1038 0400, puis utiliser un CCV aléatoire et une date d’expiration ultérieure à la date actuelle.
</i></p>

<p><i>Vous devrez vous reconnecter si vous changez de mode.</i></p>
            
            </div>';
			}

			if ($this->get_option('multi_3_enabled') === 'yes' || $this->get_option('multi_12_enabled') === 'yes') {
				$multiMode = '';
				if ($this->get_option('multi_3_enabled') === 'yes' && $this->get_option('multi_12_enabled') === 'yes') {
					$multiMode = '3x ou 12x';
				} else if ($this->get_option('multi_3_enabled') === 'yes') {
					$multiMode = '3x';
				} else if ($this->get_option('multi_12_enabled') === 'yes') {
					$multiMode = '12x';
				}

				echo '<div id="multiMode"><p>
				Le paiement en ' . $multiMode . ' s\'appliquera sur tous les paniers<br/>
				Seul le premier paiement sera réalisé lors de la commande
				</p>
				</div>';
			}

			if ($isConnected) {
				$organizationName = get_option('helloasso_organization_slug');
				$environment = ($this->get_option('testmode') === 'yes' ? '-sandbox' : '');
				$mode = $this->get_option('testmode') === 'yes' ? 'test' : 'production';
				$url = "https://admin.helloasso{$environment}.com/" . esc_html($organizationName) . "/accueil";

				echo "<p><strong>Connecté avec <a href='" . esc_html($url) . "' target='_blank'>" . esc_html($organizationName) . "</a> en mode " . esc_html($mode) . "</strong></p>";

				echo '<a href="javascript:void(0)" id="decoHelloAsso">Se déconnecter de mon asso</a>';
			}

			$enabled = $isConnected ? 1 : 0;
			$testmode = get_option('helloasso_testmode') === 'yes' ? 1 : 0;

			echo '<script defer>
                jQuery(document).ready(function($) {
                    $("#woocommerce_helloasso_enabled, #woocommerce_helloasso_testmode").change(function() {
                        var enabled = $("#woocommerce_helloasso_enabled").is(":checked") ? 1 : 0;
                        var testmode = $("#woocommerce_helloasso_testmode").is(":checked") ? 1 : 0;
						var wasEnabled = ' . esc_js($enabled) . ';
						var wasTestMode = ' . esc_js($testmode) . ';
						var buttonText = "Enregistrer les modifications";

						if (enabled == 1 && wasEnabled == 0) {
							if (testmode == 1) {
								buttonText = "Enregistrer et se connecter à HelloAsso en mode test";
							} else {
								buttonText = "Enregistrer et se connecter à HelloAsso";
							}
						} else if (enabled == 0 && wasEnabled == 1) {
							buttonText = "Enregistrer les modifications et se déconnecter";
						} else if (enabled == 1 && wasEnabled == 1) {
							if (testmode == 1 && wasTestMode == 0) {
								buttonText = "Enregistrer et activer le mode test";
							} else if (testmode == 0 && wasTestMode == 1) {
								buttonText = "Enregistrer et désactiver le mode test";
							}
						}

						$(".HaAuthorizeButtonTitle").html(buttonText);
                    });
                });
                </script>';

			echo '<script defer>
            jQuery(document).ready(function($) {
	
                $(".woocommerce-save-button").html(`   <img src="' . esc_html(plugins_url('assets/logo-ha.svg', __FILE__)) . '" alt=""
                class="HaAuthorizeButtonLogo">
                <span class="HaAuthorizeButtonTitle">' . esc_html($btnText) . '</span>`);
                $(".woocommerce-save-button").addClass("HaAuthorizeButton");
                      
                $("#decoHelloAsso").click(function() {
                    $.ajax({
                        url: "' . esc_js(get_site_url()) . '/wc-api/helloasso_deco",
                        type: "POST",
                        data: {
                            action: "helloasso_deco"
                        },
                        success: function(data) {
                            console.log(data);
                            var data = JSON.parse(data);
                            if (data.success) {
                                location.reload();
                            } else {
                                alert(data.message);
                            }
                        }
                    });
                });
            });
            </script>';
		}


		public function init_form_fields()
		{
			$this->form_fields = array(
				'enabled' => array(
					'title' => 'Activer/Désactiver',
					'label' => 'Activer HelloAsso',
					'type' => 'checkbox',
					'description' => '',
					'default' => 'no'
				),
				'multi_3_enabled' => array(
					'title' => 'Paiement en 3 fois',
					'label' => 'Activer le paiement en 3 fois',
					'type' => 'checkbox',
					'description' => 'Cette option laissera le choix au payeur de réaliser son paiement en une ou trois fois.',
					'default' => 'no',
					'desc_tip' => true,
				),
				'multi_12_enabled' => array(
					'title' => 'Paiement en 12 fois',
					'label' => 'Activer le paiement en 12 fois',
					'type' => 'checkbox',
					'description' => 'Cette option laissera le choix au payeur de réaliser son paiement en une ou douze fois.',
					'default' => 'no',
					'desc_tip' => true,
				),
				'title' => array(
					'title' => 'Titre',
					'type' => 'text',
					'description' => 'Le titre du moyen de paiement qui s\'affichera pendant le checkout.',
					'default' => 'Payer par carte bancaire avec HelloAsso',
					'custom_attributes' => array(
						'readonly' => 'readonly'
					),
					'desc_tip' => true,
				),
				'testmode' => array(
					'title' => 'Test mode',
					'label' => 'Activer le mode test',
					'type' => 'checkbox',
					'description' => 'Activer le mode test pour le paiement HelloAsso.',
					'default' => 'false',
					'desc_tip' => true,
				),
			);
		}

		public function process_admin_options()
		{
			parent::process_admin_options();

			if ($this->get_option('testmode') === 'yes') {
				$client_id = HELLOASSO_WOOCOMMERCE_CLIENT_ID_TEST;
				$client_secret = HELLOASSO_WOOCOMMERCE_CLIENT_SECRET_TEST;
				$api_url = HELLOASSO_WOOCOMMERCE_API_URL_TEST;
				$api_url_auth = HELLOASSO_WOOCOMMERCE_AUTH_URL_TEST;
			} else {
				$client_id = HELLOASSO_WOOCOMMERCE_CLIENT_ID_PROD;
				$client_secret = HELLOASSO_WOOCOMMERCE_CLIENT_SECRET_PROD;
				$api_url = HELLOASSO_WOOCOMMERCE_API_URL_PROD;
				$api_url_auth = HELLOASSO_WOOCOMMERCE_AUTH_URL_PROD;
			}

			$isConnected = false;
			if (get_option('helloasso_access_token_asso')) {
				$isConnected = true;
			}

			if ($isConnected && get_option('helloasso_testmode') == $this->get_option('testmode')) {
				return;
			}

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

			if (get_option('helloasso_testmode')) {
				update_option('helloasso_testmode', $this->get_option('testmode'));
			} else {
				add_option('helloasso_testmode', $this->get_option('testmode'));
			}

			if ($this->get_option('enabled') !== 'yes') {
				return;
			}

			helloasso_get_oauth_token($client_id, $client_secret, $api_url);

			$nonce = wp_create_nonce('helloasso_connect_return');
			$return_url = get_site_url() . '/wc-api/helloasso?nonce=' . $nonce;
			$redirect_uri_encode = urlencode($return_url);

			$code_challenge = helloasso_generate_pkce();
			$state = bin2hex(random_bytes(32));

			if (get_option('helloasso_state')) {
				update_option('helloasso_state', $state);
			} else {
				add_option('helloasso_state', $state);
			}

			$authorization_url = $api_url_auth . "authorize?client_id=$client_id&redirect_uri=$redirect_uri_encode&code_challenge=$code_challenge&code_challenge_method=S256&state=$state";

			add_option('helloasso_authorization_url', $authorization_url);

			wp_redirect($authorization_url);
			exit;
		}

		public function validate_fields()
		{
			if (isset($_GET['pay_for_order'])) {
				return true;
			}

			if (isset($_POST['billing_first_name']) && isset($_POST['billing_last_name']) && isset($_POST['billing_email'])) {
				if (!isset($_POST['woocommerce-process-checkout-nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce-process-checkout-nonce'])), 'woocommerce-process_checkout')) {
					wc_add_notice('La commande ne peut être finalisé', 'error');
				}

				$firstName = sanitize_text_field($_POST['billing_first_name']);
				$lastName = sanitize_text_field($_POST['billing_last_name']);
				$email = sanitize_text_field($_POST['billing_email']);
			} else {
				// GET request payload json
				$json = file_get_contents('php://input');
				$data = json_decode($json, true);


				$firstName = $data['billing_address']['first_name'];
				$lastName = $data['billing_address']['last_name'];
				$email = $data['billing_address']['email'];
			}

			if (preg_match('/(.)\1{2,}/', $firstName)) {
				wc_add_notice('Le prénom ne doit pas contenir 3 caractères répétitifs', 'error');
				return false;
			}

			if (preg_match('/(.)\1{2,}/', $lastName)) {
				wc_add_notice('Le nom ne doit pas contenir 3 caractères répétitifs', 'error');
				return false;
			}

			if (preg_match('/[0-9]/', $firstName)) {
				wc_add_notice('Le prénom ne doit pas contenir de chiffre', 'error');
				return false;
			}

			if (preg_match('/[0-9]/', $lastName)) {
				wc_add_notice('Le nom ne doit pas contenir de chiffre', 'error');
				return false;
			}

			if (preg_match('/[aeiouy]/i', $firstName) === 0) {
				wc_add_notice('Le prénom doit contenir au moins une voyelle', 'error');
				return false;
			}

			if (preg_match('/[aeiouy]/i', $lastName) === 0) {
				wc_add_notice('Le nom doit contenir au moins une voyelle', 'error');
				return false;
			}

			if (in_array($firstName, array('firstname', 'lastname', 'unknown', 'first_name', 'last_name', 'anonyme', 'user', 'admin', 'name', 'nom', 'prénom', 'test'))) {
				wc_add_notice('Le prénom ne peut pas être ' . $firstName, 'error');
				return false;
			}

			if (in_array($lastName, array('firstname', 'lastname', 'unknown', 'first_name', 'last_name', 'anonyme', 'user', 'admin', 'name', 'nom', 'prénom', 'test'))) {
				wc_add_notice('Le nom ne peut pas être ' . $lastName, 'error');
				return false;
			}

			if (preg_match('/![a-zA-ZéèêëáàâäúùûüçÇ\'-]/', $firstName)) {
				wc_add_notice('Le prénom ne doit pas contenir de caractères spéciaux ni de caractères n\'appartenant pas à l\'alphabet latin', 'error');
				return false;
			}

			if (preg_match('/![a-zA-ZéèêëáàâäúùûüçÇ\'-]/', $lastName)) {
				wc_add_notice('Le nom ne doit pas contenir de caractères spéciaux ni de caractères n\'appartenant pas à l\'alphabet latin', 'error');
				return false;
			}

			if ($firstName === $lastName) {
				wc_add_notice('Le prénom et le nom ne peuvent pas être identiques', 'error');
				return false;
			}

			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				wc_add_notice('L\'email n\'est pas valide', 'error');
				return false;
			}

			return true;
		}

		public function process_payment($order_id)
		{
			helloasso_log_info('Début du traitement de paiement', array(
				'order_id' => $order_id,
				'user_id' => get_current_user_id(),
				'payment_method' => 'helloasso'
			));

			helloasso_refresh_token_asso();
			$order = wc_get_order($order_id);

			if (!$order) {
				helloasso_log_error('Commande introuvable', array('order_id' => $order_id));
				return array('result' => 'failure', 'messages' => 'Commande introuvable');
			}

			helloasso_log_info('Récupération des données client', array(
				'order_id' => $order_id,
				'order_status' => $order->get_status(),
				'order_total' => $order->get_total()
			));

			if (isset($_GET['pay_for_order'])) {
				$firstName = $order->get_billing_first_name();
				$lastName = $order->get_billing_last_name();
				$email = $order->get_billing_email();
				$adress = $order->get_billing_address_1();
				$city = $order->get_billing_city();
				$zipCode = $order->get_billing_postcode();
				$countryIso = helloasso_convert_country_code($order->get_billing_country());
				$company = $order->get_billing_company();

				helloasso_log_debug('Données client récupérées depuis la commande existante', array(
					'first_name' => $firstName,
					'last_name' => $lastName,
					'email' => $email,
					'country' => $countryIso
				));
			} else {
				if (isset($_POST['billing_first_name'])) {
					if (!isset($_POST['woocommerce-process-checkout-nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce-process-checkout-nonce'])), 'woocommerce-process_checkout')) {
						helloasso_log_error('Nonce invalide lors du checkout', array('order_id' => $order_id));
						wc_add_notice('La commande ne peut être finalisé', 'error');
					}

					if (isset($_POST['billing_first_name'])) {
						$firstName = sanitize_text_field($_POST['billing_first_name']);
					} else {
						$firstName = '';
					}

					if (isset($_POST['billing_last_name'])) {
						$lastName = sanitize_text_field($_POST['billing_last_name']);
					} else {
						$lastName = '';
					}

					if (isset($_POST['billing_email'])) {
						$email = sanitize_text_field($_POST['billing_email']);
					} else {
						$email = '';
					}

					if (isset($_POST['billing_address_1'])) {
						$adress = sanitize_text_field($_POST['billing_address_1']);
					} else {
						$adress = '';
					}

					if (isset($_POST['billing_city'])) {
						$city = sanitize_text_field($_POST['billing_city']);
					} else {
						$city = '';
					}

					if (isset($_POST['billing_postcode'])) {
						$zipCode = sanitize_text_field($_POST['billing_postcode']);
					} else {
						$zipCode = '';
					}

					if (isset($_POST['billing_country'])) {
						$countryIso = helloasso_convert_country_code(sanitize_text_field($_POST['billing_country']));
					} else {
						$countryIso = '';
					}

					if (isset($_POST['billing_company'])) {
						$company = sanitize_text_field($_POST['billing_company']);
					} else {
						$company = '';
					}

					helloasso_log_debug('Données client récupérées depuis POST', array(
						'first_name' => $firstName,
						'last_name' => $lastName,
						'email' => $email,
						'country' => $countryIso
					));
				} else {
					$json = file_get_contents('php://input');
					$data = json_decode($json, true);

					$firstName = $data['billing_address']['first_name'];
					$lastName = $data['billing_address']['last_name'];
					$email = $data['billing_address']['email'];
					$adress = $data['billing_address']['address_1'];
					$city = $data['billing_address']['city'];
					$zipCode = $data['billing_address']['postcode'];
					$countryIso = helloasso_convert_country_code($data['billing_address']['country']);
					$company = $data['billing_address']['company'];

					helloasso_log_debug('Données client récupérées depuis JSON', array(
						'first_name' => $firstName,
						'last_name' => $lastName,
						'email' => $email,
						'country' => $countryIso
					));
				}
			}


			$items = $order->get_items();
			$total = $order->get_total();

			helloasso_log_info('Préparation des données de paiement', array(
				'order_id' => $order_id,
				'total' => $total,
				'items_count' => count($items)
			));

			$woocommerceOrderId = $order_id;
			$userId = $order->get_user_id();
			$nonce = wp_create_nonce('helloasso_order');
			$backUrlOrder = wc_get_checkout_url();
			$errorUrlOrder = get_site_url() . '/wc-api/helloasso_order?type=error&order_id=' . $woocommerceOrderId . '&nonce=' . $nonce;
			$returnUrlOrder = get_site_url() . '/wc-api/helloasso_order?type=return&order_id=' . $woocommerceOrderId . '&nonce=' . $nonce;

			$cartBeautifulFormat = array();

			foreach ($items as $item) {
				$product = $item->get_product();
				$cartBeautifulFormat[] = array(
					'name' => $product->get_name(),
					'quantity' => $item->get_quantity(),
					'price' => $item->get_total()
				);
			}

			$data = array(
				'totalAmount' => $total * 100,
				'initialAmount' => $total * 100,
				'itemName' => 'Commande Woocommerce ' . $woocommerceOrderId,
				'backUrl' => $backUrlOrder,
				'errorUrl' => $errorUrlOrder,
				'returnUrl' => $returnUrlOrder,
				'containsDonation' => false,
				'payer' => array(
					'firstName' => $firstName,
					'lastName' => $lastName,
					'email' => $email,
					'address' => $adress,
					'city' => $city,
					'zipCode' => $zipCode,
					'country' => $countryIso,
					'companyName' => $company,
				),
				'metadata' => array(
					'reference' => $woocommerceOrderId,
					'libelle' => 'Commande Woocommerce ' . $woocommerceOrderId,
					'userId' => $userId,
					'cart' => $cartBeautifulFormat
				)
			);

			if ($this->get_option('multi_3_enabled') === 'yes' || $this->get_option('multi_12_enabled') === 'yes') {
				$payment_type = 'one_time';

				if (isset($_POST['payment_data']) && isset($_POST['payment_data']['payment_type'])) {
					$payment_type = sanitize_text_field($_POST['payment_data']['payment_type']);
				} elseif (isset($_POST['payment_type'])) {
					$payment_type = sanitize_text_field($_POST['payment_type']);
				} elseif (isset($_POST['helloasso_payment_type'])) {
					$payment_type = sanitize_text_field($_POST['helloasso_payment_type']);
				} elseif (isset($_POST['paymentMethodData']) && isset($_POST['paymentMethodData']['payment_type'])) {
					$payment_type = sanitize_text_field($_POST['paymentMethodData']['payment_type']);
				}

				if (empty($payment_type) || $payment_type === 'one_time') {
					$input = file_get_contents('php://input');
					$request = json_decode($input, true);

					if ($request) {
						if (isset($request['payment_data']) && isset($request['payment_data']['payment_type'])) {
							$payment_type = sanitize_text_field($request['payment_data']['payment_type']);
						} elseif (isset($request['paymentMethodData']) && isset($request['paymentMethodData']['payment_type'])) {
							$payment_type = sanitize_text_field($request['paymentMethodData']['payment_type']);
						} elseif (isset($request['meta']) && isset($request['meta']['paymentMethodData']) && isset($request['meta']['paymentMethodData']['payment_type'])) {
							$payment_type = sanitize_text_field($request['meta']['paymentMethodData']['payment_type']);
						} else {
							$payment_type = helloasso_find_payment_type_recursive($request);
						}
					}
				}

				helloasso_log_info('Type de paiement détecté', array(
					'order_id' => $order_id,
					'payment_type' => $payment_type,
					'multi_3_enabled' => $this->get_option('multi_3_enabled'),
					'multi_12_enabled' => $this->get_option('multi_12_enabled')
				));

				$order->update_meta_data('helloasso_payment_type', $payment_type === 'three_times' ? '3 fois (prochaine écheance à suivre sur HelloAsso)' : ($payment_type === 'twelve_times' ? '12 fois (prochaine écheance à suivre sur HelloAsso)' : 'une fois'));
				$order->save();

				if ($payment_type === 'three_times') {
					$totalCents = round($total * 100);

					$secondAmount = floor($totalCents / 3);
					$thirdAmount = floor($totalCents / 3);
					$firstAmount = $totalCents - $secondAmount - $thirdAmount;

					$today = new DateTime();
					$secondDate = (clone $today)->modify('+1 month')->format('Y-m-d');
					$thirdDate = (clone $today)->modify('+2 months')->format('Y-m-d');

					$data['initialAmount'] = $firstAmount;

					$data['terms'] = [
						[
							'date' => $secondDate,
							'amount' => $secondAmount,
						],
						[
							'date' => $thirdDate,
							'amount' => $thirdAmount,
						],
					];

					helloasso_log_info('Paiement en 3 fois configuré', array(
						'order_id' => $order_id,
						'first_amount' => $firstAmount,
						'second_amount' => $secondAmount,
						'third_amount' => $thirdAmount
					));
				} elseif ($payment_type === 'twelve_times') {
					$totalCents = round($total * 100);

					$monthlyAmount = floor($totalCents / 12);
					$firstAmount = $totalCents - ($monthlyAmount * 11);

					$data['initialAmount'] = $firstAmount;

					$data['terms'] = [];
					$today = new DateTime();

					for ($i = 1; $i <= 11; $i++) {
						$paymentDate = (clone $today)->modify("+$i month")->format('Y-m-d');
						$data['terms'][] = [
							'date' => $paymentDate,
							'amount' => $monthlyAmount,
						];
					}

					helloasso_log_info('Paiement en 12 fois configuré', array(
						'order_id' => $order_id,
						'first_amount' => $firstAmount,
						'monthly_amount' => $monthlyAmount
					));
				}
			}

			$bearerToken = get_option('helloasso_access_token_asso');
			$isInTestMode = get_option('helloasso_testmode');

			helloasso_log_info('Configuration API HelloAsso', array(
				'order_id' => $order_id,
				'test_mode' => $isInTestMode,
				'has_token' => !empty($bearerToken),
				'organization_slug' => get_option('helloasso_organization_slug')
			));

			if ('yes' === $isInTestMode) {
				$api_url = HELLOASSO_WOOCOMMERCE_API_URL_TEST;
			} else {
				$api_url = HELLOASSO_WOOCOMMERCE_API_URL_PROD;
			}

			$url = $api_url . 'v5/organizations/' . get_option('helloasso_organization_slug') . '/checkout-intents';

			helloasso_log_info('Appel API HelloAsso', array(
				'order_id' => $order_id,
				'url' => $url,
				'api_url' => $api_url
			));

			$response = wp_remote_post($url, helloasso_get_args_post_token($data, $bearerToken));

			if (is_wp_error($response)) {
				helloasso_log_error('Erreur lors de l\'appel API HelloAsso', array(
					'order_id' => $order_id,
					'error' => $response->get_error_message(),
					'error_code' => $response->get_error_code()
				));
				echo 'Erreur : ' . esc_html($response->get_error_message());
			}

			$response_body = wp_remote_retrieve_body($response);
			$response_code = wp_remote_retrieve_response_code($response);

			helloasso_log_info('Réponse API HelloAsso reçue', array(
				'order_id' => $order_id,
				'response_code' => $response_code,
				'response_body_length' => strlen($response_body)
			));

			if ($response_code !== 200) {
				helloasso_log_error('Erreur API HelloAsso', array(
					'order_id' => $order_id,
					'response_code' => $response_code,
					'response_body' => $response_body
				));
			}

			$response_data = json_decode($response_body);

			if (!$response_data || !isset($response_data->redirectUrl)) {
				helloasso_log_error('Réponse API invalide', array(
					'order_id' => $order_id,
					'response_body' => $response_body
				));
			}

			helloasso_log_info('Paiement traité avec succès', array(
				'order_id' => $order_id,
				'redirect_url' => $response_data->redirectUrl ?? 'unknown'
			));

			return array(
				'result' => 'success',
				'redirect' => json_decode($response_body)->redirectUrl
			);
		}
	}
}
