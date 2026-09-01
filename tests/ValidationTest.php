<?php

namespace Helloasso\HelloassoPaymentsForWoocommerce\Tests;

require_once dirname(__DIR__) . '/helper/helloasso-woocommerce-validation.php';

/**
 * Tests de non-régression pour le Bug 2 : la regex de validation des
 * noms/prénoms devait rejeter les caractères spéciaux (par exemple les
 * points) mais laissait passer certaines entrées invalides à cause d'un
 * `!` placé à l'intérieur de la classe de caractères au lieu d'une
 * négation `^` correcte.
 */
class ValidationTest extends TestCase
{
	public function test_valid_names_are_accepted(): void
	{
		$this->assertNull(helloasso_validate_billing_names('Jean', 'Dupont'));
		$this->assertNull(helloasso_validate_billing_names('Éloïse', "O'Connor"));
		$this->assertNull(helloasso_validate_billing_names('Anne-Marie', 'Le Gall'));
	}

	public function test_name_with_dot_is_rejected(): void
	{
		$error = helloasso_validate_billing_names('Jean.', 'Dupont');
		$this->assertNotNull($error);
		$this->assertStringContainsString('caractères spéciaux', $error);
	}

	public function test_name_with_digits_is_rejected(): void
	{
		$error = helloasso_validate_billing_names('Jean1', 'Dupont');
		$this->assertNotNull($error);
		$this->assertStringContainsString('chiffre', $error);
	}

	public function test_name_with_repeated_characters_is_rejected(): void
	{
		$error = helloasso_validate_billing_names('Jeaaan', 'Dupont');
		$this->assertNotNull($error);
		$this->assertStringContainsString('répétitifs', $error);
	}

	public function test_name_without_vowel_is_rejected(): void
	{
		$error = helloasso_validate_billing_names('Jrdlm', 'Dupont');
		$this->assertNotNull($error);
		$this->assertStringContainsString('voyelle', $error);
	}

	public function test_reserved_word_is_rejected(): void
	{
		$error = helloasso_validate_billing_names('test', 'Dupont');
		$this->assertNotNull($error);
		$this->assertStringContainsString('ne peut pas être', $error);
	}

	public function test_identical_first_and_last_name_is_rejected(): void
	{
		$error = helloasso_validate_billing_names('Dupont', 'Dupont');
		$this->assertNotNull($error);
		$this->assertStringContainsString('identiques', $error);
	}

	/**
	 * @dataProvider specialCharacterProvider
	 */
	public function test_names_with_special_characters_are_rejected(string $name): void
	{
		$error = helloasso_validate_name($name, 'le prénom');
		$this->assertNotNull($error, "Le nom '$name' aurait dû être rejeté");
	}

	public function specialCharacterProvider(): array
	{
		return [
			'point' => ['Jean.'],
			'arobase' => ['Jean@'],
			'diese' => ['Jean#'],
			'chevrons' => ['<Jean>'],
		];
	}
}
