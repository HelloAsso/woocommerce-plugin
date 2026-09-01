<?php

namespace Helloasso\HelloassoPaymentsForWoocommerce\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Classe de base pour les tests unitaires du plugin HelloAsso.
 *
 * Active/désactive Brain Monkey autour de chaque test et fournit des stubs
 * par défaut pour les fonctions de logging du plugin (helloasso_log_*), afin
 * que les tests n'aient pas à les redéfinir systématiquement.
 */
abstract class TestCase extends BaseTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		Monkey\setUp();

		foreach (['helloasso_log_debug', 'helloasso_log_info', 'helloasso_log_warning', 'helloasso_log_error'] as $logFunction) {
			Monkey\Functions\when($logFunction)->justReturn(null);
		}
	}

	protected function tearDown(): void
	{
		Monkey\tearDown();
		parent::tearDown();
	}
}
