<?php
/*
 * Plugin Name: WooCommerce HelloAsso Paiements
 * Plugin URI: https://helloasso.org
 * Description: Payer avec HelloAsso
 * Author: Yohann KIPFER
 * Author URI: https://kipdev.io
 * Version: 1.0.0
 */

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
require_once('helper/helloasso-woocommerce-config.php');
require_once('helper/helloasso-woocommerce-helper.php');
require_once('cron/helloasso-woocommerce-cron.php');
require_once('helloasso-api/helloasso-woocommerce-api.php');
require_once('wc-api/helloasso-woocommerce-wc-api.php');

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'helloasso_add_gateway_class');
function helloasso_add_gateway_class($gateways)
{
    $gateways[] = 'WC_HelloAsso_Gateway';
    return $gateways;
}



add_action('plugins_loaded', 'helloasso_init_gateway_class');

function helloasso_init_gateway_class()
{

    class WC_HelloAsso_Gateway extends WC_Payment_Gateway
    {

        public function __construct()
        {

            $this->id = 'helloasso';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = 'Paiement avec HelloAsso';
            $this->method_description = 'Accepter les paiements directement depuis votre association HelloAsso';

            $this->supports = array(
                'products'
            );



            $this->init_form_fields();

       
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));


            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));


        }

        public function admin_options()
        {
            // Check if we have helloasso_access_token_asso in the options
            $isConnected = false;
            if (get_option('helloasso_access_token_asso')) {
                $isConnected = true;
            }

            if (isset($_GET['msg']) && $_GET['msg'] === 'error_connect') {
                echo '<div class="notice notice-error is-dismissible">
                <p>Erreur lors de la connexion à HelloAsso. Veuillez réessayer.</p>
                </div>';
            }

            if (isset($_GET['msg']) && $_GET['msg'] === 'success_connect') {
                echo '<div class="notice notice-success is-dismissible">
                <p>Connexion à HelloAsso réussie.</p>
                </div>';
            }

            echo '<h3>' . esc_html($this->method_title) . '</h3>';
            echo wp_kses_post(wpautop($this->method_description));


            echo '
            <p>
            <strong>
            Pour accepter les paiements avec HelloAsso, vous devrez vous connecter à votre compte HelloAsso. <br/>
            Le mode test vous enverra sur la page de connexion HelloAsso en mode test. <br/>
            Vous devrez vous reconnecter si vous changez de mode.

            </strong></p>
            

        
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
                $btnText = "Enregistrer les modifications";
            } else {
                $btnText = "Enregistrer et se connecter à HelloAsso";
            }
            $styleTestMode = $this->get_option('testmode') === 'yes' ? '' : 'display: none;';

            echo '<div id="testMode" style="' . $styleTestMode . '"><p><strong>Pour le mode test voici les cartes : </strong></p>
            
                <p> Carte de test : 4242 4242 4242 4242 pour Stripe, 5017 6791 1038 0400 pour Lemonway</p></div>';


            if ($isConnected) {
                $organizationName = get_option('helloasso_organization_name');
                echo '<a href="javascript:void(0)" id="decoHelloAsso">Déconnecter mon association ' . $organizationName . ' </a>';

                $mode = get_option('helloasso_testmode');

                if ($mode === 'yes') {
                    $mode = 1;
                } else {
                    $mode = 0;
                }

                echo '<script defer>
                jQuery(document).ready(function($) {
                    // check when woocommerce_helloasso_testmode is changed
                    $("#woocommerce_helloasso_testmode").change(function() {
                        var testmode = $("#woocommerce_helloasso_testmode").is(":checked") ? 1 : 0;

                        if(testmode == 0) {
                            $("#testMode").hide();
                        } else {
                            $("#testMode").show();
                        }

                        if(testmode == ' . $mode . ') {
                            $(".HaAuthorizeButtonTitle").html("Enregistrer les modifications");
                        } else {
                            $(".HaAuthorizeButtonTitle").html("Enregistrer et se connecter à HelloAsso");
                        }
                   
                    });
                });
                </script>';
            }

            echo '<script defer>
            jQuery(document).ready(function($) {
                $(".woocommerce-save-button").html(`   <img src="https://api.helloasso.com/v5/DocAssets/logo-ha.svg" alt=""
                class="HaAuthorizeButtonLogo">
                <span class="HaAuthorizeButtonTitle">' . $btnText . '</span>`);
                $(".woocommerce-save-button").addClass("HaAuthorizeButton");
                      
                $("#decoHelloAsso").click(function() {
                    $.ajax({
                        url: "' . get_site_url() . '/wc-api/helloasso_deco",
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
                    'title'       => 'Activer/Désactiver',
                    'label'       => 'Activer HelloAsso',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Titre',
                    'type'        => 'text',
                    'description' => 'Le titre du moyen de paiement qui s\'affichera pendant le checkout.',
                    'default'     => 'Paiement par carte bancaire',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'La description du moyen de paiement qui s\'affichera pendant le checkout.',
                    'default'     => 'Payer directement sur notre association via HelloAsso.',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Activer le mode test',
                    'type'        => 'checkbox',
                    'description' => 'Activer le mode test pour le paiement HelloAsso.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
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
            delete_option('helloasso_domain_registered');
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

            if (get_option('helloasso_testmode')) {
                update_option('helloasso_testmode', $this->get_option('testmode'));
            } else {
                add_option('helloasso_testmode', $this->get_option('testmode'));
            }


            $access_token = get_oauth_token($client_id, $client_secret, $api_url);

            $url_website = get_site_url();
            $domain_registered = get_option('helloasso_domain_registered');

            if (!$domain_registered) {
                $url = $api_url . 'v5/partners/me/api-clients';

                $data = array(
                    'domain' => $url_website
                );

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Authorization: Bearer ' . $access_token,
                    'Content-Type: application/json'
                ));

                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

                $response = curl_exec($ch);
                curl_close($ch);


                if ($response === false) {
                    return null;
                }

                $data = json_decode($response);

                add_option('helloasso_domain_registered', $url_website);
            }


            $return_url = get_site_url() . '/wc-api/helloasso';
            $redirect_uri_encode = urlencode($return_url);

            $code_challenge =  helloasso_generate_pkce();
            $state = bin2hex(random_bytes(32));

            if (get_option('hello_asso_state')) {
                update_option('hello_asso_state', $state);
            } else {
                add_option('hello_asso_state', $state);
            }

            $authorization_url = $api_url_auth . "authorize?client_id=$client_id&redirect_uri=$redirect_uri_encode&code_challenge=$code_challenge&code_challenge_method=S256&state=$state";

            add_option('hello_asso_authorization_url', $authorization_url);
         
            wp_redirect($authorization_url);
        }



        public function payment_fields()
        {

            echo '
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

         
     

        }


        public function payment_scripts()
        {
        }


        public function validate_fields()
        {
            if (isset($_GET['pay_for_order'])) {
                return true;
            }
            $firstName = $_POST['billing_first_name'];
            $lastName = $_POST['billing_last_name'];
            $email = $_POST['billing_email'];

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

            if (in_array($firstName, array("firstname", "lastname", "unknown", "first_name", "last_name", "anonyme", "user", "admin", "name", "nom", "prénom", "test"))) {
                wc_add_notice('Le prénom ne peut pas être ' . $firstName, 'error');
                return false;
            }

            if (in_array($lastName, array("firstname", "lastname", "unknown", "first_name", "last_name", "anonyme", "user", "admin", "name", "nom", "prénom", "test"))) {
                wc_add_notice('Le nom ne peut pas être ' . $lastName, 'error');
                return false;
            }

            if (preg_match('/[\'\-\ç]/', $firstName)) {
                wc_add_notice('Le prénom ne doit pas contenir de caractères spéciaux', 'error');
                return false;
            }

            if (preg_match('/[\'\-\ç]/', $lastName)) {
                wc_add_notice('Le nom ne doit pas contenir de caractères spéciaux', 'error');
                return false;
            }

            if (preg_match('/[^a-zA-Z]/', $firstName)) {
                wc_add_notice('Le prénom ne doit pas contenir de caractères n\'appartenant pas à l\'alphabet latin', 'error');
                return false;
            }

            if (preg_match('/[^a-zA-Z]/', $lastName)) {
                wc_add_notice('Le nom ne doit pas contenir de caractères n\'appartenant pas à l\'alphabet latin', 'error');
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
            refresh_token_asso();
            $order = wc_get_order( $order_id );
            if (isset($_GET['pay_for_order'])) {
                $firstName = $order->get_billing_first_name();
                $lastName = $order->get_billing_last_name();
                $email = $order->get_billing_email();
                $adress = $order->get_billing_address_1();
                $city = $order->get_billing_city();
                $zipCode = $order->get_billing_postcode();
                $countryIso = helloasso_convert_country_code($order->get_billing_country());
                $company = $order->get_billing_company();
            } else {
                $firstName = $_POST['billing_first_name'];
                $lastName = $_POST['billing_last_name'];
                $email = $_POST['billing_email'];
                $adress = $_POST['billing_address_1'];
                $city = $_POST['billing_city'];
                $zipCode = $_POST['billing_postcode'];
                $countryIso = helloasso_convert_country_code($_POST['billing_country']);
                $company = $_POST['billing_company'];
            }

            $items = $order->get_items();
            $total = $order->get_total();
          
         
            $woocommerceOrderId = $order_id;
            $userId = $order->get_user_id();
            $backUrlOrder = wc_get_checkout_url();
            $errorUrlOrder = get_site_url() . '/wc-api/helloasso_order?type=error&order_id=' . $woocommerceOrderId;
            $returnUrlOrder = get_site_url() . '/wc-api/helloasso_order?type=return&order_id=' . $woocommerceOrderId;

            $cartBeautifulFormat = array();

            foreach ($items as $item) {
                $product = $item->get_product();
                $cartBeautifulFormat[] = array(
                    "name" => $product->get_name(),
                    "quantity" => $item->get_quantity(),
                    "price" => $item->get_total()
                );
            }
          

            $data = array(
                "totalAmount" => $total * 100,
                "initialAmount" => $total * 100,
                "itemName" => "Commande Woocommerce " . $woocommerceOrderId,
                "backUrl" => $backUrlOrder,
                "errorUrl" => $errorUrlOrder,
                "returnUrl" => $returnUrlOrder,
                "containsDonation" => false,
                "payer" => array(
                    "firstName" => $firstName,
                    "lastName" =>  $lastName,
                    "email" => $email,
                    "address" => $adress,
                    "city" => $city,
                    "zipCode" => $zipCode,
                    "country" => $countryIso,
                    "companyName" => $company,
                ),
                "metadata" => array(
                    "reference" => $woocommerceOrderId,
                    "libelle" => "Commande Woocommerce " . $woocommerceOrderId,
                    "userId" => $userId,
                    "cart" => $cartBeautifulFormat
                )
            );

        
            $bearerToken = get_option('helloasso_access_token_asso');
            $isInTestMode = get_option('helloasso_testmode');

            if ($isInTestMode === 'yes') {
                $api_url = HELLOASSO_WOOCOMMERCE_API_URL_TEST;
            } else {
                $api_url = HELLOASSO_WOOCOMMERCE_API_URL_PROD;
            }

            $url = $api_url . "v5/organizations/" . get_option('helloasso_organization_slug') . "/checkout-intents";
        
            $ch = curl_init($url);
        
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json",
                "Authorization: Bearer " . $bearerToken
            ));
        
            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                echo "Erreur Curl : " . curl_error($ch);
            }
        
            curl_close($ch);
            
            return array(
                'result'   => 'success',
                'redirect' => json_decode($response)->redirectUrl
            );
        

        }

    }
}
