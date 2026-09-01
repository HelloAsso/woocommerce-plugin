<?php
/**
 * Bootstrap PHPUnit pour les tests unitaires du plugin HelloAsso.
 *
 * On utilise Brain\Monkey pour simuler les fonctions WordPress/WooCommerce
 * (get_option, wp_remote_post, wc_add_notice, ...) sans avoir besoin d'une
 * installation WordPress complète. Cela permet de tester la logique métier
 * du plugin (rafraîchissement de token, validation des champs, ...) de
 * façon rapide et isolée.
 */

define('ABSPATH', __DIR__ . '/');

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Fonctions utilisées par le plugin mais non fournies par Brain Monkey /
// WordPress dans ce contexte de test : on les déclare une seule fois ici
// avec un comportement neutre par défaut. Les tests peuvent surcharger leur
// comportement via Brain\Monkey\Functions::when()/expect() car ces stubs
// sont eux-mêmes interceptés par le loader de fonctions de Brain Monkey
// lorsqu'ils sont appelés depuis le code testé (voir TestCase::setUp()).
