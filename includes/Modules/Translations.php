<?php

namespace Sina_Plugin\Modules;

use Sina_Plugin\Contracts\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disables automatic translation updates.
 */
final class Translations implements ModuleInterface {

	public function is_enabled() {
		return true;
	}

	public function register() {
		add_filter( 'auto_update_translation', '__return_false' );
		add_filter( 'async_update_translation', '__return_false' );
	}
}
