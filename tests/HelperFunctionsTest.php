<?php

namespace Helloasso\HelloassoPaymentsForWoocommerce\Tests;

/**
 * Tests des fonctions utilitaires pures de helper/helloasso-woocommerce-helper.php :
 * conversion de code pays ISO 3166-1 alpha-2 vers alpha-3, et détection récursive
 * du type de paiement (one_time / three_times / twelve_times) dans une charge utile
 * potentiellement imbriquée (POST, JSON du panier bloc Gutenberg, etc.).
 */
class HelperFunctionsTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		if (!function_exists('helloasso_convert_country_code')) {
			require_once dirname(__DIR__) . '/helper/helloasso-woocommerce-helper.php';
		}
	}

	public function test_converts_known_iso2_code_to_iso3(): void
	{
		$this->assertSame('FRA', helloasso_convert_country_code('FR'));
		$this->assertSame('USA', helloasso_convert_country_code('US'));
		$this->assertSame('GBR', helloasso_convert_country_code('GB'));
	}

	public function test_returns_input_unchanged_when_code_unknown(): void
	{
		$this->assertSame('ZZ', helloasso_convert_country_code('ZZ'));
		$this->assertSame('', helloasso_convert_country_code(''));
	}

	public function test_is_case_sensitive_on_input_code(): void
	{
		// La table de correspondance utilise des clés en majuscules ; un code
		// en minuscules ne doit pas être reconnu et doit être renvoyé tel quel.
		$this->assertSame('fr', helloasso_convert_country_code('fr'));
	}

	public function test_finds_payment_type_at_top_level(): void
	{
		$this->assertSame('three_times', helloasso_find_payment_type_recursive(array(
			'payment_type' => 'three_times',
		)));
	}

	public function test_finds_payment_type_nested_in_payment_data(): void
	{
		$payload = array(
			'payment_data' => array(
				'payment_type' => 'twelve_times',
			),
		);

		$this->assertSame('twelve_times', helloasso_find_payment_type_recursive($payload));
	}

	public function test_finds_payment_type_deeply_nested(): void
	{
		$payload = array(
			'meta' => array(
				'paymentMethodData' => array(
					'payment_type' => 'three_times',
				),
			),
		);

		$this->assertSame('three_times', helloasso_find_payment_type_recursive($payload));
	}

	public function test_defaults_to_one_time_when_absent(): void
	{
		$this->assertSame('one_time', helloasso_find_payment_type_recursive(array(
			'foo' => 'bar',
			'nested' => array('baz' => 'qux'),
		)));
	}

	public function test_defaults_to_one_time_when_input_is_not_array(): void
	{
		$this->assertSame('one_time', helloasso_find_payment_type_recursive(null));
		$this->assertSame('one_time', helloasso_find_payment_type_recursive('not-an-array'));
	}
}
