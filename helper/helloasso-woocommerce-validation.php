<?php

/**
 * Validation "pure" (sans dépendance WordPress) des champs nom/prénom
 * utilisés lors du paiement HelloAsso. Extraite de
 * WC_HelloAsso_Gateway::validate_fields() afin de pouvoir être testée
 * unitairement sans charger WordPress/WooCommerce.
 */

if (!function_exists('helloasso_validate_name')) {
	/**
	 * Valide un nom ou prénom selon les règles métier HelloAsso.
	 *
	 * @param string $name  Valeur à valider (prénom ou nom).
	 * @param string $label Libellé utilisé dans les messages d'erreur ("prénom" ou "nom").
	 *
	 * @return string|null Message d'erreur en français si invalide, null si valide.
	 */
	function helloasso_validate_name(string $name, string $label): ?string
	{
		if (preg_match('/(.)\1{2,}/', $name)) {
			return ucfirst($label) . ' ne doit pas contenir 3 caractères répétitifs';
		}

		if (preg_match('/[0-9]/', $name)) {
			return ucfirst($label) . ' ne doit pas contenir de chiffre';
		}

		if (preg_match('/[aeiouy]/i', $name) === 0) {
			return ucfirst($label) . ' doit contenir au moins une voyelle';
		}

		$reservedWords = array('firstname', 'lastname', 'unknown', 'first_name', 'last_name', 'anonyme', 'user', 'admin', 'name', 'nom', 'prénom', 'test');
		if (in_array($name, $reservedWords, true)) {
			return ucfirst($label) . ' ne peut pas être ' . $name;
		}

		if (preg_match('/[^\p{L}\'\- ]/u', $name)) {
			return ucfirst($label) . ' ne doit pas contenir de caractères spéciaux ni de caractères n\'appartenant pas à l\'alphabet latin';
		}

		return null;
	}
}

if (!function_exists('helloasso_validate_billing_names')) {
	/**
	 * Valide la paire prénom/nom (règles communes aux deux champs).
	 *
	 * @return string|null Premier message d'erreur rencontré, null si valide.
	 */
	function helloasso_validate_billing_names(string $firstName, string $lastName): ?string
	{
		$firstNameError = helloasso_validate_name($firstName, 'le prénom');
		if ($firstNameError !== null) {
			return $firstNameError;
		}

		$lastNameError = helloasso_validate_name($lastName, 'le nom');
		if ($lastNameError !== null) {
			return $lastNameError;
		}

		if ($firstName === $lastName) {
			return 'Le prénom et le nom ne peuvent pas être identiques';
		}

		return null;
	}
}
