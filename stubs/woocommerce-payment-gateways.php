<?php

declare(strict_types=1);

if (!class_exists('WC_Payment_Gateways')) {
    class WC_Payment_Gateways
    {
        public static function instance(): self
        {
            return new self();
        }

        /**
         * @return array<string, object>
         */
        public function payment_gateways(): array
        {
            return [];
        }
    }
}
