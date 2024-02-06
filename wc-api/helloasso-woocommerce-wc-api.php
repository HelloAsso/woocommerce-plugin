<?php


/* Return of the HelloAsso API */

add_action('woocommerce_api_helloasso', 'helloasso_endpoint');

function helloasso_endpoint()
{
    $isInTestMode = get_option('helloasso_testmode');

    if ($isInTestMode === 'yes') {
        $client_id = HELLOASSO_WOOCOMMERCE_CLIENT_ID_TEST;
        $client_secret = HELLOASSO_WOOCOMMERCE_CLIENT_SECRET_TEST;
        $api_url = HELLOASSO_WOOCOMMERCE_API_URL_TEST;
    } else {
        $client_id = HELLOASSO_WOOCOMMERCE_CLIENT_ID_PROD;
        $client_secret = HELLOASSO_WOOCOMMERCE_CLIENT_SECRET_PROD;
        $api_url = HELLOASSO_WOOCOMMERCE_API_URL_PROD;
    }


    if (!isset($_GET['code']) || !isset($_GET['state'])) {
        wp_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=error_connect');
    }

    $code = sanitize_text_field($_GET['code']);
    $state = sanitize_text_field($_GET['state']);

    if ($state !== get_option('hello_asso_state')) {
        wp_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=error_connect');
        exit;
    }

    $url = $api_url . 'oauth2/token';

    $data = array(
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => get_site_url() . '/wc-api/helloasso',
        'code_verifier' => get_option('helloasso_code_verifier')
    );


    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        wp_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=error_connect');
    }

    $data = json_decode($response);


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
        $chOrga = curl_init($urlOrga);
        curl_setopt($chOrga, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chOrga, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $data->access_token
        ));

        $responseOrga = curl_exec($chOrga);
        curl_close($chOrga);



        if ($responseOrga === false) {
            wp_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=error_connect');
        }

        $dataOrga = json_decode($responseOrga);

        if (isset($dataOrga->name)) {
            delete_option('helloasso_organization_name');
            add_option('helloasso_organization_name', $dataOrga->name);
        }


        $urlNotif = $api_url . 'v5/partners/me/api-notifications';

        $dataNotifSend = array(
            'url' => get_site_url() . '/wc-api/helloasso_webhook'
        );

        $chNotif = curl_init($urlNotif);
        curl_setopt($chNotif, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chNotif, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $data->access_token,
            'Content-Type: application/json'
        ));

        curl_setopt($chNotif, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($chNotif, CURLOPT_POSTFIELDS, json_encode($dataNotifSend));

        $responseNotif = curl_exec($chNotif);
        curl_close($chNotif);

      
      
        if ($responseNotif === false) {
            return null;
        }

        $dataNotif = json_decode($responseNotif);


        delete_option('helloasso_webhook_url');
        add_option('helloasso_webhook_url', get_site_url() . '/wc-api/helloasso_webhook');



         wp_redirect(get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=helloasso&msg=success_connect');
    } else {
    }


    exit;
}


add_action('woocommerce_api_helloasso_deco', 'helloasso_endpoint_deco');

function helloasso_endpoint_deco()
{
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
    echo json_encode(array('success' => true, 'message' => 'Vous avez bien été déconnecté de votre compte HelloAsso'));
    exit;
}

add_action('woocommerce_api_helloasso_webhook', 'helloasso_endpoint_webhook');

function helloasso_endpoint_webhook()
{
 
    $data = json_decode(file_get_contents('php://input'), true);
    add_option('helloasso_webhook_data', json_encode($data));
    $order = wc_get_order($data['metadata']['reference']);
    $order->update_status('processing');

    exit;
}

add_action('woocommerce_api_helloasso_order', 'helloasso_endpoint_order');

function helloasso_endpoint_order()
{
 
    $type = sanitize_text_field($_GET['type']);
    $order_id = sanitize_text_field($_GET['order_id']);

    if($type === 'error') {
        $order = wc_get_order($order_id);
        $order->update_status('failed');
        wp_redirect($order->get_checkout_order_received_url());
    }

    if($type === 'return') {
        $code = sanitize_text_field($_GET['code']);

        if($code === "succeeded") {
            $order = wc_get_order($order_id);
            $order->update_status('pending');
            wp_redirect($order->get_checkout_order_received_url());
        }

        if($code === "refused") {
            $order = wc_get_order($order_id);
            $order->update_status('failed');
            wp_redirect($order->get_checkout_order_received_url());

        }
    }
}