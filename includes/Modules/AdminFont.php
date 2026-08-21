<?php

namespace Sina_Plugin\Modules;

use Sina_Plugin\Contracts\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies Farhang font across the WordPress admin (icons excluded).
 */
final class AdminFont implements ModuleInterface {

	public function is_enabled() {
		return is_admin() || $this->is_login_screen();
	}

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue() {
		wp_enqueue_style(
			'sina-admin-font',
			SINA_PLUGIN_URL . 'assets/css/admin-font.css',
			array(),
			SINA_PLUGIN_VERSION
		);

		$faces = $this->build_font_face_css();

		if ( '' !== $faces ) {
			wp_add_inline_style( 'sina-admin-font', $faces );
		}
	}

	/**
	 * @return string
	 */
	private function build_font_face_css() {
		$weights = array(
			300 => 'Farhang2FaNum-Light-300.woff2',
			400 => 'Farhang2FaNum-Regular-400.woff2',
			600 => 'Farhang2FaNum-DemiBold-600.woff2',
			700 => 'Farhang2FaNum-Bold-700.woff2',
			800 => 'Farhang2FaNum-ExtraBold-800.woff2',
		);

		$css = '';

		foreach ( $weights as $weight => $file ) {
			$path = SINA_PLUGIN_PATH . 'assets/fonts/farhang/' . $file;

			if ( ! is_readable( $path ) ) {
				continue;
			}

			$url = SINA_PLUGIN_URL . 'assets/fonts/farhang/' . rawurlencode( $file );

			$css .= sprintf(
				"@font-face{font-family:'Farhang';font-style:normal;font-weight:%d;font-display:swap;src:url('%s') format('woff2');}",
				(int) $weight,
				esc_url( $url )
			);
		}

		return $css;
	}

	/**
	 * @return bool
	 */
	private function is_login_screen() {
		return false !== strpos( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 'wp-login.php' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}
}
