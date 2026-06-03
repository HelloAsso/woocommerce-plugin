<?php

declare(strict_types=1);

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

if (!class_exists(AbstractPaymentMethodType::class)) {
    abstract class AbstractPaymentMethodType
    {
        protected  $name = '';

        /** @var array<string, mixed> */
        protected array $settings = [];

        /** @var array<string, mixed> */
        protected array $data = [];

        protected $script_version = null;
        protected $asset_api = null;

     

        public function is_active()
        {
            return true;
        }

        public function get_payment_method_script_handles()
        {
            return [];
        }

        public function get_payment_method_data()
        {
            return [];
        }

        public function get_name(): string
        {
            return $this->name;
        }
    }
}