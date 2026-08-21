<?php

namespace Sina_Plugin\Modules\WooCommerce;

use Sina_Plugin\Contracts\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a subtotal-based packaging fee.
 */
final class PackagingFee implements ModuleInterface {

	const OPTION_ENABLED = 'sina_packaging_fee_enabled';
	const OPTION_TIERS   = 'sina_packaging_fee_tiers';
	const FEE_NAME       = 'هزینه بسته‌بندی';

	public function is_enabled() {
		return class_exists( 'WooCommerce' );
	}

	public function register() {
		( new PackagingFeeSettings() )->register();
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_packaging_fee' ) );
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

		if ( 'yes' !== get_option( self::OPTION_ENABLED, 'no' ) ) {
			return;
		}

		$tiers = self::get_tiers();

		if ( empty( $tiers ) ) {
			return;
		}

		$fee_amount = self::resolve_fee( (float) $cart->get_subtotal(), $tiers );

		if ( $fee_amount <= 0 ) {
			return;
		}

		$cart->add_fee( self::FEE_NAME, $fee_amount, false );
	}

	/**
	 * @return array<int, array{max: float, fee: float}>
	 */
	public static function get_tiers() {
		$tiers = get_option( self::OPTION_TIERS, array() );

		if ( ! is_array( $tiers ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $tiers as $tier ) {
			if ( ! is_array( $tier ) || ! isset( $tier['max'], $tier['fee'] ) ) {
				continue;
			}

			$normalized[] = array(
				'max' => (float) $tier['max'],
				'fee' => (float) $tier['fee'],
			);
		}

		usort(
			$normalized,
			static function ( $a, $b ) {
				return $a['max'] <=> $b['max'];
			}
		);

		return $normalized;
	}

	/**
	 * Pick fee for subtotal: first tier whose max >= subtotal, else last tier.
	 *
	 * @param float                                     $subtotal Cart product subtotal.
	 * @param array<int, array{max: float, fee: float}> $tiers    Sorted tiers.
	 * @return float
	 */
	public static function resolve_fee( $subtotal, array $tiers ) {
		if ( empty( $tiers ) ) {
			return 0.0;
		}

		foreach ( $tiers as $tier ) {
			if ( $subtotal <= $tier['max'] ) {
				return $tier['fee'];
			}
		}

		$last = end( $tiers );

		return (float) $last['fee'];
	}
}
