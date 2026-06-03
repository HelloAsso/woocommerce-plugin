<?php

declare(strict_types=1);


if (!class_exists('WC_Payment_Gateway')) {
    class WC_Payment_Gateway
    {
        /** @var string */
        public $id = '';

        /** @var string */
        public $plugin_id = 'woocommerce_';

        /** @var bool */
        public $has_fields = false;

        /** @var string */
        public $method_title = '';

        /** @var string */
        public $method_description = '';

        /** @var string[] */
        public $supports = [];

        /** @var array<string, mixed> */
        public $settings = [];

        /** 
         * @var <string|int, mixed>
         */
        public $form_fields = [];

        /** @var string */
        public $title = '';

        /** @var string */
        public $description = '';

        /** @var string */
        public $enabled = '';

        /** @var string */
        public $order_button_text = '';

        /** @var string */
        public $icon = '';

        public function __construct()
        {
        }

        /**
         * Initialise settings form fields.
         *
         * @return mixed
         */
        public function init_form_fields()
        {
        }
        public function init_settings(): void
        {
        }

        /**
         * @param string $key
         * @param mixed $empty_value
         * @return mixed
         */
        public function get_option($key, $empty_value = null)
        {
            return $empty_value;
        }

        /**
         * @param string $key
         * @param mixed $value
         * @return void
         */
        public function update_option($key, $value)
        {
        }

        /**
         * @return bool
         */
        public function process_admin_options()
        {
            return true;
        }

        /**
         * @param int $order_id
         * @return array<string|int, mixed>
         */
        public function process_payment($order_id)
        {
            return [];
        }

        public function validate_fields(): bool
        {
            return true;
        }

        /**
         * @return mixed
         */
        public function payment_fields()
        {
        }
        /**
         * @return mixed
         */
        public function admin_options()
        {
        }

        public function get_title(): string
        {
            return $this->title;
        }

        public function get_description(): string
        {
            return $this->description;
        }

        public function get_icon(): string
        {
            return $this->icon;
        }

        public function get_return_url($order = null): string
        {
            return '';
        }

        public function is_available(): bool
        {
            return true;
        }

        /**
         * @return array<string, mixed>
         */
        public function get_tokens(): array
        {
            return [];
        }

        public function add_error(string $error): void
        {
        }

        public function get_description_html(): string
        {
            return $this->description;
        }

      public function generate_settings_html(array $form_fields = [], bool $echo = true): string
        {
            return '';
        }
     
    }
}