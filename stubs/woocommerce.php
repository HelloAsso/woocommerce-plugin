<?php

declare(strict_types=1);

if (!class_exists('WC_Payment_Gateway')) {
    abstract class WC_Payment_Gateway
    {
        /** @var array<string, mixed> */
        public $settings = [];

        /** @var string */
        public $id = '';

        /** @var string */
        public $method_title = '';

        /** @var string */
        public $method_description = '';

        public function __construct()
        {
        }

        public function init_form_fields(): void
        {
        }

        public function init_settings(): void
        {
        }

        /**
         * @return mixed
         */
        public function get_option($key, $empty_value = null)
        {
            return $empty_value;
        }

        public function process_admin_options(): bool
        {
            return true;
        }
    }
}

if (!function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency(): string
    {
        return 'EUR';
    }
}