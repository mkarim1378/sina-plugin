<?php

namespace Sina_Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PSR-4 style autoloader for the Sina_Plugin namespace.
 */
final class Autoloader {

	/**
	 * @var string
	 */
	private static $base_dir;

	/**
	 * @param string $base_dir Absolute path to the includes directory.
	 */
	public static function register( $base_dir ) {
		self::$base_dir = trailingslashit( $base_dir );

		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * @param string $class Fully-qualified class name.
	 */
	public static function autoload( $class ) {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = self::$base_dir . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
