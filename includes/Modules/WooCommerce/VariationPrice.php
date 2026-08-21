<?php

namespace Sina_Plugin\Modules\WooCommerce;

use Sina_Plugin\Contracts\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode: [variation_price_by_attr ...] for variable product prices.
 */
final class VariationPrice implements ModuleInterface {

	public function is_enabled() {
		return class_exists( 'WooCommerce' );
	}

	public function register() {
		add_shortcode( 'variation_price_by_attr', array( $this, 'render' ) );
	}

	/**
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			$product = wc_get_product( get_the_ID() );
		}

		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return '';
		}

		$attributes = is_array( $atts ) ? $atts : array();

		if ( empty( $attributes ) ) {
			return '';
		}

		$data_store   = \WC_Data_Store::load( 'product' );
		$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

		if ( ! $variation_id ) {
			return '';
		}

		$variation = wc_get_product( $variation_id );

		return $variation ? $variation->get_price_html() : '';
	}
}
