<?php

namespace Sina_Plugin;

use Sina_Plugin\Contracts\ModuleInterface;
use Sina_Plugin\Modules\AdminFont;
use Sina_Plugin\Modules\Auth;
use Sina_Plugin\Modules\Editor;
use Sina_Plugin\Modules\Translations;
use Sina_Plugin\Modules\WooCommerce\Checkout;
use Sina_Plugin\Modules\WooCommerce\PackagingFee;
use Sina_Plugin\Modules\WooCommerce\VariationPrice;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin orchestrator.
 *
 * To add a feature: create a module under Modules/, then list its class below.
 */
final class Plugin {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Feature modules. Add or remove classes here to change behavior.
	 *
	 * @var array<class-string<ModuleInterface>>
	 */
	private $modules = array(
		AdminFont::class,
		Auth::class,
		Editor::class,
		Translations::class,
		VariationPrice::class,
		Checkout::class,
		PackagingFee::class,
	);

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	public function boot() {
		foreach ( $this->modules as $module_class ) {
			$module = new $module_class();

			if ( ! $module instanceof ModuleInterface || ! $module->is_enabled() ) {
				continue;
			}

			$module->register();
		}
	}
}
