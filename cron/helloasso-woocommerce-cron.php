<?php

function cron_refresh_token_hello_asso()
{
    refresh_token_asso();
}

if (!wp_next_scheduled('cron_refresh_token_hello_asso__hook')) {
    wp_schedule_event(strtotime('00:00:00'), 'daily', 'cron_refresh_token_hello_asso__hook');
}

function refresh_token_asso() {
    $helloasso_refresh_token_asso = get_option('helloasso_refresh_token_asso');

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

    if ($helloasso_refresh_token_asso) {
        $url = $api_url . 'oauth2/token';

        $data = array(
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $helloasso_refresh_token_asso
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response);
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
