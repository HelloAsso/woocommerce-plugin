<?php

declare(strict_types=1);

namespace Automattic\WooCommerce\Blocks\Payments;

if (!class_exists(PaymentMethodRegistry::class)) {
    class PaymentMethodRegistry
    {
        public function register(object $payment_method): void
        {
        }
    }
}