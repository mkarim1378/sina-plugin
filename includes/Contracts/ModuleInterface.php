<?php

namespace Sina_Plugin\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ModuleInterface {

	/**
	 * Whether this module should register its hooks.
	 *
	 * @return bool
	 */
	public function is_enabled();

	/**
	 * Register hooks, shortcodes, and other module wiring.
	 */
	public function register();
}
