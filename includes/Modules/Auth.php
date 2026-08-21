<?php

namespace Sina_Plugin\Modules;

use Sina_Plugin\Contracts\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extends WordPress auth cookie lifetime.
 */
final class Auth implements ModuleInterface {

	/**
	 * Login cookie lifetime in days.
	 */
	const EXPIRATION_DAYS = 30;

	public function is_enabled() {
		return true;
	}

	public function register() {
		add_filter( 'auth_cookie_expiration', array( $this, 'extend_cookie_expiration' ) );
	}

	/**
	 * @param int $expirein Default expiration in seconds.
	 * @return int
	 */
	public function extend_cookie_expiration( $expirein ) {
		return self::EXPIRATION_DAYS * DAY_IN_SECONDS;
	}
}
