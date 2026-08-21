<?php

namespace Sina_Plugin\Modules\WooCommerce;

use Sina_Plugin\Contracts\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customizes WooCommerce checkout fields based on cart contents.
 */
final class Checkout implements ModuleInterface {

	/**
	 * Product category IDs that skip address fields when alone in cart.
	 *
	 * @var int[]
	 */
	const SPECIAL_CATEGORY_IDS = array( 25, 26, 27 );

	/**
	 * Billing fields kept when cart has only special-category products.
	 *
	 * @var string[]
	 */
	const SPECIAL_ALLOWED_FIELDS = array(
		'billing_first_name',
		'billing_last_name',
		'billing_phone',
	);

	public function is_enabled() {
		return class_exists( 'WooCommerce' );
	}

	public function register() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ) );
		add_filter( 'woocommerce_billing_fields', array( $this, 'make_email_optional' ), 10, 1 );
	}

	/**
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function customize_checkout_fields( $fields ) {
		if ( $this->cart_has_only_special_products() ) {
			return $this->apply_special_checkout_fields( $fields );
		}

		return $this->apply_standard_checkout_fields( $fields );
	}

	/**
	 * @param array $fields Billing fields.
	 * @return array
	 */
	public function make_email_optional( $fields ) {
		if ( isset( $fields['billing_email'] ) ) {
			$fields['billing_email']['required'] = false;
		}

		return $fields;
	}

	/**
	 * @return bool
	 */
	private function cart_has_only_special_products() {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! has_term( self::SPECIAL_CATEGORY_IDS, 'product_cat', $cart_item['data']->get_id() ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	private function apply_special_checkout_fields( $fields ) {
		foreach ( $fields['billing'] as $key => $field ) {
			if ( ! in_array( $key, self::SPECIAL_ALLOWED_FIELDS, true ) ) {
				unset( $fields['billing'][ $key ] );
				continue;
			}

			$fields['billing'][ $key ]['required'] = true;
		}

		unset( $fields['shipping'] );
		unset( $fields['order']['order_comments'] );

		return $fields;
	}

	/**
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	private function apply_standard_checkout_fields( $fields ) {
		unset( $fields['billing']['billing_address_2'] );
		unset( $fields['billing']['billing_country'] );
		unset( $fields['billing']['billing_email'] );
		unset( $fields['order']['order_comments'] );

		$fields['billing']['billing_address_1']['label']       = 'آدرس پستی';
		$fields['billing']['billing_address_1']['placeholder'] = 'آدرس کامل پستی خود را بنویسید';
		$fields['billing']['billing_address_1']['required']    = true;

		$fields['billing']['billing_first_name']['required'] = true;
		$fields['billing']['billing_last_name']['required']  = true;
		$fields['billing']['billing_phone']['required']      = true;

		return $fields;
	}
}
