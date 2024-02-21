<?php

function helloasso_get_args_post_urlencode($data)
{
    $args = array(
        'timeout' => 45, // Default to 45 seconds.
        'redirection' => 0,
        'httpversion' => '1.0',
        'sslverify' => false,
        'blocking' => true,
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
        ),
        'body' => http_build_query($data),
        'cookies' => array(),
        'user-agent' => 'PHP ' . PHP_VERSION . '/WooCommerce ' . get_option('woocommerce_db_version'),
    );

    return $args;
}

function helloasso_get_args_post_urlencode_token($data, $token)
{
    $args = array(
        'timeout' => 45, // Default to 45 seconds.
        'redirection' => 0,
        'httpversion' => '1.0',
        'sslverify' => false,
        'blocking' => true,
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
            'Authorization' => 'Bearer ' . $token,
        ),
        'body' => http_build_query($data),
        'cookies' => array(),
        'user-agent' => 'PHP ' . PHP_VERSION . '/WooCommerce ' . get_option('woocommerce_db_version'),
    );

    return $args;
}


function helloasso_get_args_post($data)
{
    $args = array(
        'timeout' => 45, // Default to 45 seconds.
        'redirection' => 0,
        'httpversion' => '1.0',
        'sslverify' => false,
        'blocking' => true,
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'body' => $data,
        'cookies' => array(),
        'user-agent' => 'PHP ' . PHP_VERSION . '/WooCommerce ' . get_option('woocommerce_db_version'),
    );

    return $args;
}


function helloasso_get_args_post_token($data, $token)
{
    $args = array(
        'timeout' => 45, // Default to 45 seconds.
        'redirection' => 0,
        'httpversion' => '1.0',
        'sslverify' => false,
        'blocking' => true,
        'headers' => array(
            'Content-type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ),
        'body' => wp_json_encode($data),
        'cookies' => array(),
        'user-agent' => 'PHP ' . PHP_VERSION . '/WooCommerce ' . get_option('woocommerce_db_version'),
    );

    return $args;
}

function helloasso_get_args_put_token($data, $token)
{
    $args = array(
        'timeout' => 45, // Default to 45 seconds.
        'redirection' => 0,
        'method' => 'PUT',
        'httpversion' => '1.0',
        'sslverify' => false,
        'blocking' => true,
        'headers' => array(
            'Content-type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ),
        'body' => wp_json_encode($data),
        'cookies' => array(),
        'user-agent' => 'PHP ' . PHP_VERSION . '/WooCommerce ' . get_option('woocommerce_db_version'),
    );

    return $args;
}

function helloasso_get_args_get_token($token)
{
    $args = array(
        'timeout' => 45,
        'redirection' => 0,
        'httpversion' => '1.0',
        'sslverify' => false,
        'blocking' => true,
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
        ),
        'cookies' => array(),
        'user-agent' => 'PHP ' . PHP_VERSION . '/WooCommerce ' . get_option('woocommerce_db_version'),
    );

    return $args;
}
