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

	const COST_TYPE_FREE    = 'free';
	const COST_TYPE_COLLECT = 'collect';
	const COST_TYPE_AMOUNT  = 'amount';

	/**
	 * @var array<string, string>
	 */
	const METHOD_LABELS = array(
		'post'   => 'پست',
		'tipax'  => 'تیپاکس',
		'chapar' => 'چاپار',
		'pickup' => 'تحویل حضوری',
	);

	/**
	 * @var array<string, string>
	 */
	const COST_TYPE_LABELS = array(
		self::COST_TYPE_FREE    => 'رایگان',
		self::COST_TYPE_COLLECT => 'پس کرایه',
		self::COST_TYPE_AMOUNT  => 'مبلغ مشخص',
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
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'filter_shipping_method_label' ), 10, 2 );
		add_filter( 'woocommerce_order_shipping_to_display', array( $this, 'filter_order_shipping_display' ), 10, 2 );
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

			$cost_type = self::normalize_cost_type( $method['cost_type'], $key );
			$cost      = self::COST_TYPE_AMOUNT === $cost_type ? (float) $method['shipping_cost'] : 0.0;
			$rate_id   = self::rate_id( $key );
			$rate      = new \WC_Shipping_Rate(
				$rate_id,
				$method['label'],
				$cost,
				array(),
				self::PICKUP_KEY === $key ? 'local_pickup' : self::METHOD_ID,
				0
			);
			$rate->add_meta_data( 'sina_method', $key );
			$rate->add_meta_data( 'sina_cost_type', $cost_type );
			$built[ $rate_id ] = $rate;
		}

		return $built;
	}

	/**
	 * Show رایگان / پس کرایه / مبلغ instead of WooCommerce's generic free label.
	 *
	 * @param string            $label  Full shipping method label.
	 * @param \WC_Shipping_Rate $method Shipping rate.
	 * @return string
	 */
	public function filter_shipping_method_label( $label, $method ) {
		if ( ! $method instanceof \WC_Shipping_Rate ) {
			return $label;
		}

		$cost_type = self::get_rate_meta( $method, 'sina_cost_type' );

		if ( ! is_string( $cost_type ) || '' === $cost_type ) {
			$key = self::get_rate_meta( $method, 'sina_method' );

			if ( ! is_string( $key ) || '' === $key ) {
				$key = self::method_key_from_rate_id( $method->get_id() );
			}

			if ( is_string( $key ) && '' !== $key ) {
				$methods   = self::get_methods();
				$cost_type = isset( $methods[ $key ]['cost_type'] ) ? $methods[ $key ]['cost_type'] : '';
			}
		}

		if ( ! is_string( $cost_type ) || ! isset( self::COST_TYPE_LABELS[ $cost_type ] ) ) {
			return $label;
		}

		$name = $method->get_label();

		if ( self::COST_TYPE_AMOUNT === $cost_type ) {
			return $name . ': ' . wc_price( (float) $method->get_cost() );
		}

		return $name . ': ' . self::COST_TYPE_LABELS[ $cost_type ];
	}

	/**
	 * Keep پس کرایه visible on orders/emails instead of Free.
	 *
	 * @param string    $shipping Formatted shipping total HTML.
	 * @param \WC_Order $order    Order.
	 * @return string
	 */
	public function filter_order_shipping_display( $shipping, $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return $shipping;
		}

		foreach ( $order->get_shipping_methods() as $item ) {
			if ( self::COST_TYPE_COLLECT === $item->get_meta( 'sina_cost_type' ) ) {
				return esc_html( self::COST_TYPE_LABELS[ self::COST_TYPE_COLLECT ] );
			}
		}

		return $shipping;
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
	 * @return array<string, array{label: string, enabled: bool, cost_type: string, shipping_cost: float, packaging_cost: float}>
	 */
	public static function get_methods() {
		$stored    = get_option( self::OPTION_METHODS, null );
		$has_saved = is_array( $stored );

		if ( ! $has_saved ) {
			$stored = array();
		}

		$methods = array();

		foreach ( self::METHOD_LABELS as $key => $label ) {
			$row  = isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? $stored[ $key ] : array();
			$cost = self::PICKUP_KEY === $key ? 0.0 : (float) ( isset( $row['shipping_cost'] ) ? $row['shipping_cost'] : 0 );

			$methods[ $key ] = array(
				'label'          => $label,
				'enabled'        => $has_saved ? ! empty( $row['enabled'] ) : true,
				'cost_type'      => self::resolve_cost_type( $row, $key, $cost ),
				'shipping_cost'  => $cost,
				'packaging_cost' => (float) ( isset( $row['packaging_cost'] ) ? $row['packaging_cost'] : 0 ),
			);
		}

		return $methods;
	}

	/**
	 * @param string $type Cost type.
	 * @param string $key  Method key.
	 * @return string
	 */
	public static function normalize_cost_type( $type, $key ) {
		if ( self::PICKUP_KEY === $key ) {
			return self::COST_TYPE_FREE;
		}

		if ( is_string( $type ) && isset( self::COST_TYPE_LABELS[ $type ] ) ) {
			return $type;
		}

		return self::COST_TYPE_FREE;
	}

	/**
	 * @param array  $row  Stored method row.
	 * @param string $key  Method key.
	 * @param float  $cost Shipping cost amount.
	 * @return string
	 */
	private static function resolve_cost_type( array $row, $key, $cost ) {
		if ( self::PICKUP_KEY === $key ) {
			return self::COST_TYPE_FREE;
		}

		if ( isset( $row['cost_type'] ) ) {
			return self::normalize_cost_type( $row['cost_type'], $key );
		}

		// Legacy rows only had a number: any positive amount stays "amount", zero was free.
		return $cost > 0 ? self::COST_TYPE_AMOUNT : self::COST_TYPE_FREE;
	}

	/**
	 * @param string $key Method key.
	 * @return string
	 */
	public static function rate_id( $key ) {
		return self::RATE_PREFIX . $key;
	}

	/**
	 * WC_Shipping_Rate has get_meta_data(), not get_meta() (unlike WC_Data).
	 *
	 * @param \WC_Shipping_Rate $rate Shipping rate.
	 * @param string            $key  Meta key.
	 * @return mixed|null
	 */
	private static function get_rate_meta( \WC_Shipping_Rate $rate, $key ) {
		$meta = $rate->get_meta_data();

		return is_array( $meta ) && array_key_exists( $key, $meta ) ? $meta[ $key ] : null;
	}

	/**
	 * @param string $rate_id Full rate id (e.g. sina_shipping_post).
	 * @return string Method key or empty.
	 */
	private static function method_key_from_rate_id( $rate_id ) {
		$rate_id = (string) $rate_id;

		if ( 0 !== strpos( $rate_id, self::RATE_PREFIX ) ) {
			return '';
		}

		$key = substr( $rate_id, strlen( self::RATE_PREFIX ) );

		return isset( self::METHOD_LABELS[ $key ] ) ? $key : '';
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

		$has_shippable    = false;
		$all_special_only = true;

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
