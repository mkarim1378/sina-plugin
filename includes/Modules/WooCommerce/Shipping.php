<?php

namespace Sina_Plugin\Modules\WooCommerce;

use Sina_Plugin\Contracts\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin-managed shipping methods and per-method packaging fees.
 */
final class Shipping implements ModuleInterface {

	const OPTION_METHODS = 'sina_shipping_methods';
	const RATE_PREFIX    = 'sina_shipping_';
	const METHOD_ID      = 'sina_shipping';
	const FEE_NAME       = 'هزینه بسته‌بندی';
	const PICKUP_KEY     = 'pickup';

	/**
	 * @var array<string, string>
	 */
	const METHOD_LABELS = array(
		'post'   => 'پست',
		'tipax'  => 'تیپاکس',
		'chapar' => 'چاپار',
		'pickup' => 'تحویل حضوری',
	);

	public function is_enabled() {
		return class_exists( 'WooCommerce' );
	}

	public function register() {
		( new ShippingSettings() )->register();

		add_filter( 'wc_shipping_enabled', array( $this, 'force_shipping_enabled' ) );
		add_filter( 'pre_option_woocommerce_shipping_cost_requires_address', array( $this, 'always_show_rates' ) );
		add_filter( 'woocommerce_shipping_hide_rates_when_free', '__return_false' );
		add_filter( 'woocommerce_package_rates', array( $this, 'replace_package_rates' ), 100, 2 );
		add_filter( 'woocommerce_shipping_chosen_method', array( $this, 'prefer_non_pickup_default' ), 10, 3 );
		add_filter( 'woocommerce_cart_needs_shipping_address', array( $this, 'maybe_skip_shipping_address' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_packaging_fee' ) );
	}

	/**
	 * Keep shipping available whenever at least one plugin method is enabled.
	 *
	 * @param bool $enabled WooCommerce shipping enabled flag.
	 * @return bool
	 */
	public function force_shipping_enabled( $enabled ) {
		foreach ( self::get_methods() as $method ) {
			if ( ! empty( $method['enabled'] ) ) {
				return true;
			}
		}

		return $enabled;
	}

	/**
	 * Show rates before a destination address is entered.
	 *
	 * @return string
	 */
	public function always_show_rates() {
		return 'no';
	}

	/**
	 * @param array $rates   Existing package rates.
	 * @param array $package Shipping package.
	 * @return array
	 */
	public function replace_package_rates( $rates, $package ) {
		if ( ! $this->package_needs_physical_shipping( $package ) ) {
			return array();
		}

		$built = array();

		foreach ( self::get_methods() as $key => $method ) {
			if ( empty( $method['enabled'] ) ) {
				continue;
			}

			$rate_id = self::rate_id( $key );
			$cost    = self::PICKUP_KEY === $key ? 0.0 : (float) $method['shipping_cost'];
			$rate    = new \WC_Shipping_Rate(
				$rate_id,
				$method['label'],
				$cost,
				array(),
				self::PICKUP_KEY === $key ? 'local_pickup' : self::METHOD_ID,
				0
			);
			$rate->add_meta_data( 'sina_method', $key );
			$built[ $rate_id ] = $rate;
		}

		return $built;
	}

	/**
	 * Avoid auto-selecting free pickup when other methods exist.
	 *
	 * @param string                         $default Default chosen rate id.
	 * @param array<string, \WC_Shipping_Rate> $rates Available rates.
	 * @param string|bool                    $chosen Chosen rate from session.
	 * @return string
	 */
	public function prefer_non_pickup_default( $default, $rates, $chosen ) {
		if ( is_string( $chosen ) && isset( $rates[ $chosen ] ) ) {
			return $chosen;
		}

		$pickup_id = self::rate_id( self::PICKUP_KEY );

		foreach ( $rates as $rate_id => $rate ) {
			if ( $pickup_id !== $rate_id ) {
				return $rate_id;
			}
		}

		return $default;
	}

	/**
	 * @param bool $needs Whether a shipping address is required.
	 * @return bool
	 */
	public function maybe_skip_shipping_address( $needs ) {
		if ( self::PICKUP_KEY === self::get_chosen_method_key() ) {
			return false;
		}

		return $needs;
	}

	/**
	 * @param \WC_Cart $cart Cart instance.
	 */
	public function add_packaging_fee( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! $cart || $cart->is_empty() ) {
			return;
		}

		$key = self::get_chosen_method_key();

		if ( '' === $key ) {
			return;
		}

		$methods = self::get_methods();

		if ( empty( $methods[ $key ]['enabled'] ) ) {
			return;
		}

		$fee_amount = (float) $methods[ $key ]['packaging_cost'];

		if ( $fee_amount <= 0 ) {
			return;
		}

		$cart->add_fee( self::FEE_NAME, $fee_amount, false );
	}

	/**
	 * @return array<string, array{label: string, enabled: bool, shipping_cost: float, packaging_cost: float}>
	 */
	public static function get_methods() {
		$stored = get_option( self::OPTION_METHODS, null );
		$has_saved = is_array( $stored );

		if ( ! $has_saved ) {
			$stored = array();
		}

		$methods = array();

		foreach ( self::METHOD_LABELS as $key => $label ) {
			$row = isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? $stored[ $key ] : array();

			$methods[ $key ] = array(
				'label'          => $label,
				'enabled'        => $has_saved ? ! empty( $row['enabled'] ) : true,
				'shipping_cost'  => self::PICKUP_KEY === $key ? 0.0 : (float) ( isset( $row['shipping_cost'] ) ? $row['shipping_cost'] : 0 ),
				'packaging_cost' => (float) ( isset( $row['packaging_cost'] ) ? $row['packaging_cost'] : 0 ),
			);
		}

		return $methods;
	}

	/**
	 * @param string $key Method key.
	 * @return string
	 */
	public static function rate_id( $key ) {
		return self::RATE_PREFIX . $key;
	}

	/**
	 * @return string
	 */
	public static function get_chosen_method_key() {
		if ( ! WC()->session ) {
			return '';
		}

		$chosen = WC()->session->get( 'chosen_shipping_methods' );

		if ( empty( $chosen ) || ! is_array( $chosen ) ) {
			return '';
		}

		$rate_id = explode( ':', (string) reset( $chosen ) );
		$rate_id = $rate_id[0];

		if ( 0 !== strpos( $rate_id, self::RATE_PREFIX ) ) {
			return '';
		}

		$key = substr( $rate_id, strlen( self::RATE_PREFIX ) );

		return isset( self::METHOD_LABELS[ $key ] ) ? $key : '';
	}

	/**
	 * @param array $package Shipping package.
	 * @return bool
	 */
	private function package_needs_physical_shipping( $package ) {
		if ( empty( $package['contents'] ) || ! is_array( $package['contents'] ) ) {
			return false;
		}

		$has_shippable     = false;
		$all_special_only  = true;

		foreach ( $package['contents'] as $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;

			if ( $product && $product->needs_shipping() ) {
				$has_shippable = true;
			}

			$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;

			if ( ! $product_id || ! has_term( Checkout::SPECIAL_CATEGORY_IDS, 'product_cat', $product_id ) ) {
				$all_special_only = false;
			}
		}

		if ( ! $has_shippable || $all_special_only ) {
			return false;
		}

		return true;
	}
}
