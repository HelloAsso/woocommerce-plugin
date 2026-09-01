<?php

namespace Helloasso\HelloassoPaymentsForWoocommerce\Tests;

/**
 * Vérifie que la durée de vie du refresh_token HelloAsso est bien de 29 jours
 * (et non 30), conformément à la confirmation métier reçue de HelloAsso.
 */
class RefreshTokenLifetimeTest extends TestCase
{
	public function test_refresh_token_lifetime_is_29_days(): void
	{
		if (!defined('HELLOASSO_REFRESH_TOKEN_LIFETIME')) {
			\Brain\Monkey\Functions\when('wp_next_scheduled')->justReturn(true);
			\Brain\Monkey\Functions\when('wp_schedule_event')->justReturn(true);
			require_once dirname(__DIR__) . '/cron/helloasso-woocommerce-cron.php';
		}

		$this->assertSame(29 * 24 * 60 * 60, HELLOASSO_REFRESH_TOKEN_LIFETIME);
		$this->assertSame(2505600, HELLOASSO_REFRESH_TOKEN_LIFETIME);
	}
}
