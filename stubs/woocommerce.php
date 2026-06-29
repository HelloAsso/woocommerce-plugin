<?php

declare(strict_types=1);


define('HELLOASSO_PLUGIN_DIR', '/tmp/helloasso-plugin/');

if (!function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency(): string
    {
        return 'EUR';
    }
}
// wc_add_notice 
if (!function_exists('wc_add_notice')) {
    function wc_add_notice(string $message, string $notice_type = 'success'): void
    {
        // This is a stub function for wc_add_notice. In a real implementation, this would add the notice to WooCommerce's notice system.
        // For testing purposes, you can log the notice or simply ignore it.
    }
}
// wc_get_order
if (!function_exists('wc_get_order')) {
    function wc_get_order($order_id)
    {
        // This is a stub function for wc_get_order. In a real implementation, this would retrieve the order object from WooCommerce.
        // For testing purposes, you can return a mock order object or simply return null.
        return null;
    }
}
// wc_get_checkout_url
if (!function_exists('wc_get_checkout_url')) {
    function wc_get_checkout_url(): string
    {
        // This is a stub function for wc_get_checkout_url. In a real implementation, this would return the URL of the checkout page.
        // For testing purposes, you can return a placeholder URL or simply return an empty string.
        return 'https://example.com/checkout';
    }
}