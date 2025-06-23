<?php
if (! defined('ABSPATH')) {
	exit; //Exit if accessed directly
}
function helloasso_get_args_post_urlencode($data)
{
	helloasso_log_debug('Préparation requête POST URL-encoded', array(
		'data_keys' => array_keys($data),
		'data_count' => count($data)
	));

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

function helloasso_get_args_post($data)
{
	helloasso_log_debug('Préparation requête POST JSON', array(
		'data_length' => strlen($data),
		'data_preview' => substr($data, 0, 200) . '...'
	));

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
	helloasso_log_debug('Préparation requête POST avec token', array(
		'data_keys' => array_keys($data),
		'has_token' => !empty($token),
		'token_preview' => !empty($token) ? substr($token, 0, 10) . '...' : 'none'
	));

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
	helloasso_log_debug('Préparation requête PUT avec token', array(
		'data_keys' => array_keys($data),
		'has_token' => !empty($token)
	));

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
	helloasso_log_debug('Préparation requête GET avec token', array(
		'has_token' => !empty($token),
		'token_preview' => !empty($token) ? substr($token, 0, 10) . '...' : 'none'
	));

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
