<?php

namespace Helloasso\HelloassoPaymentsForWoocommerce\Tests;

use Brain\Monkey\Functions;

/**
 * Tests de non-régression pour le Bug 1 : rotation concurrente du
 * refresh_token HelloAsso provoquant des 401 et un access_token invalidé.
 *
 * Ces tests couvrent :
 *  - le fait qu'on ne rafraîchit pas un token encore valide ;
 *  - le rafraîchissement effectif quand le token est expiré/proche de l'être ;
 *  - le verrou empêchant deux rafraîchissements concurrents (mutex best-effort) ;
 *  - la pose du flag "connexion perdue" en cas de refresh_token invalide (401/400) ;
 *  - la levée de ce flag après un rafraîchissement réussi.
 */
class RefreshTokenTest extends TestCase
{
	/** @var array<string, mixed> */
	private array $options = array();

	protected function setUp(): void
	{
		parent::setUp();

		if (!defined('HELLOASSO_WOOCOMMERCE_CLIENT_ID_TEST')) {
			define('HELLOASSO_WOOCOMMERCE_CLIENT_ID_TEST', 'client_id_test');
			define('HELLOASSO_WOOCOMMERCE_CLIENT_SECRET_TEST', 'client_secret_test');
			define('HELLOASSO_WOOCOMMERCE_API_URL_TEST', 'https://api.helloasso-sandbox.com/');
			define('HELLOASSO_WOOCOMMERCE_CLIENT_ID_PROD', 'client_id_prod');
			define('HELLOASSO_WOOCOMMERCE_CLIENT_SECRET_PROD', 'client_secret_prod');
			define('HELLOASSO_WOOCOMMERCE_API_URL_PROD', 'https://api.helloasso.com/');
		}

		Functions\when('wp_next_scheduled')->justReturn(true);
		Functions\when('wp_schedule_event')->justReturn(true);
		require_once dirname(__DIR__) . '/helper/helloasso-woocommerce-api-call.php';
		require_once dirname(__DIR__) . '/cron/helloasso-woocommerce-cron.php';

		$this->options = array();

		Functions\when('get_option')->alias(function ($name) {
			return $this->options[$name] ?? false;
		});
		Functions\when('update_option')->alias(function ($name, $value) {
			$this->options[$name] = $value;
			return true;
		});
		Functions\when('delete_option')->alias(function ($name) {
			unset($this->options[$name]);
			return true;
		});
		Functions\when('add_option')->alias(function ($name) {
			if (array_key_exists($name, $this->options)) {
				return false;
			}
			$this->options[$name] = time();
			return true;
		});
	}

	public function test_does_not_refresh_when_token_still_valid(): void
	{
		$this->options['helloasso_access_token_asso'] = 'still-valid-token';
		$this->options['helloasso_token_expires_in_asso'] = time() + 3600;

		Functions\expect('wp_remote_post')->never();

		$token = helloasso_refresh_token_asso();

		$this->assertSame('still-valid-token', $token);
	}

	public function test_refreshes_when_token_expired(): void
	{
		$this->options['helloasso_access_token_asso'] = 'expired-token';
		$this->options['helloasso_token_expires_in_asso'] = time() - 10;
		$this->options['helloasso_refresh_token_asso'] = 'refresh-token-value';
		$this->options['helloasso_testmode'] = 'yes';

		Functions\expect('wp_remote_post')->once()->andReturn('raw-response');
		Functions\when('is_wp_error')->justReturn(false);
		Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(array(
			'access_token' => 'new-access-token',
			'refresh_token' => 'new-refresh-token',
			'expires_in' => 3600,
		)));
		Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

		$token = helloasso_refresh_token_asso();

		$this->assertSame('new-access-token', $token);
		$this->assertSame('new-access-token', $this->options['helloasso_access_token_asso']);
		$this->assertSame('new-refresh-token', $this->options['helloasso_refresh_token_asso']);
		$this->assertArrayNotHasKey('helloasso_connection_lost_asso', $this->options);
	}

	public function test_successful_refresh_clears_connection_lost_flag(): void
	{
		$this->options['helloasso_access_token_asso'] = 'expired-token';
		$this->options['helloasso_token_expires_in_asso'] = time() - 10;
		$this->options['helloasso_refresh_token_asso'] = 'refresh-token-value';
		$this->options['helloasso_connection_lost_asso'] = array('time' => time());

		Functions\when('wp_remote_post')->justReturn('raw-response');
		Functions\when('is_wp_error')->justReturn(false);
		Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(array(
			'access_token' => 'new-access-token',
			'refresh_token' => 'new-refresh-token',
			'expires_in' => 3600,
		)));
		Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

		helloasso_refresh_token_asso();

		$this->assertArrayNotHasKey('helloasso_connection_lost_asso', $this->options);
	}

	public function test_invalid_refresh_token_sets_connection_lost_flag(): void
	{
		$this->options['helloasso_access_token_asso'] = 'expired-token';
		$this->options['helloasso_token_expires_in_asso'] = time() - 10;
		$this->options['helloasso_refresh_token_asso'] = 'stale-refresh-token';

		Functions\when('wp_remote_post')->justReturn('raw-response');
		Functions\when('is_wp_error')->justReturn(false);
		Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(array('error' => 'invalid_grant')));
		Functions\when('wp_remote_retrieve_response_code')->justReturn(401);

		$token = helloasso_refresh_token_asso();

		$this->assertNull($token);
		$this->assertArrayHasKey('helloasso_connection_lost_asso', $this->options);
		$this->assertSame(401, $this->options['helloasso_connection_lost_asso']['response_code']);
	}

	public function test_network_error_returns_null_and_releases_lock(): void
	{
		$this->options['helloasso_access_token_asso'] = 'expired-token';
		$this->options['helloasso_token_expires_in_asso'] = time() - 10;
		$this->options['helloasso_refresh_token_asso'] = 'refresh-token-value';

		$wpError = $this->getMockBuilder(\stdClass::class)->addMethods(['get_error_message'])->getMock();
		$wpError->method('get_error_message')->willReturn('network down');

		Functions\when('wp_remote_post')->justReturn($wpError);
		Functions\when('is_wp_error')->justReturn(true);

		$token = helloasso_refresh_token_asso();

		$this->assertNull($token);
		$this->assertArrayNotHasKey('helloasso_refresh_lock_asso', $this->options);
	}

	public function test_concurrent_refresh_is_blocked_by_lock(): void
	{
		$this->options['helloasso_access_token_asso'] = 'expired-token';
		$this->options['helloasso_token_expires_in_asso'] = time() - 10;
		$this->options['helloasso_refresh_token_asso'] = 'refresh-token-value';
		// Simule un verrou déjà détenu par une autre requête, posé récemment (non périmé).
		$this->options['helloasso_refresh_lock_asso'] = time();

		Functions\expect('wp_remote_post')->never();

		$token = helloasso_refresh_token_asso();

		$this->assertSame('expired-token', $token);
	}
}
