<?php

namespace Sina_Plugin\Modules\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce settings tab: آکادمی سینا - بسته‌بندی
 */
final class PackagingFeeSettings extends \WC_Settings_Page {

	public function __construct() {
		$this->id    = 'sina_packaging';
		$this->label = 'آکادمی سینا - بسته‌بندی';

		parent::__construct();

		add_action( 'woocommerce_admin_field_sina_packaging_tiers', array( $this, 'output_tiers_field' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';

		if ( $this->id !== $tab ) {
			return;
		}

		wp_enqueue_style(
			'sina-packaging-fee-admin',
			SINA_PLUGIN_URL . 'assets/css/admin-packaging-fee.css',
			array(),
			SINA_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'sina-packaging-fee-admin',
			SINA_PLUGIN_URL . 'assets/js/admin-packaging-fee.js',
			array(),
			SINA_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * @return array
	 */
	protected function get_settings_for_default_section() {
		return $this->get_settings();
	}

	/**
	 * @param string $section Section id (unused; single section tab).
	 * @return array
	 */
	public function get_settings( $section = null ) {
		return array(
			array(
				'title' => 'هزینه بسته‌بندی',
				'type'  => 'title',
				'desc'  => 'بر اساس جمع محصولات سبد (subtotal)، هزینه بسته‌بندی به‌صورت پلکانی به سبد اضافه می‌شود. اگر مبلغ سبد از بالاترین آستانه بیشتر باشد، هزینه آخرین سطح اعمال می‌شود.',
				'id'    => 'sina_packaging_fee_title',
			),
			array(
				'title'   => 'فعال بودن',
				'desc'    => 'افزودن هزینه بسته‌بندی به سبد خرید',
				'id'      => PackagingFee::OPTION_ENABLED,
				'default' => 'no',
				'type'    => 'checkbox',
			),
			array(
				'type' => 'sina_packaging_tiers',
				'id'   => PackagingFee::OPTION_TIERS,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'sina_packaging_fee_title',
			),
		);
	}

	/**
	 * @param array $setting Field config.
	 */
	public function output_tiers_field( $setting ) {
		$tiers    = PackagingFee::get_tiers();
		$currency = get_woocommerce_currency_symbol();

		if ( empty( $tiers ) ) {
			$tiers = array(
				array(
					'max' => '',
					'fee' => '',
				),
			);
		}

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label>سطوح هزینه</label>
			</th>
			<td class="forminp">
				<table class="widefat striped sina-packaging-tiers" id="sina-packaging-tiers">
					<thead>
						<tr>
							<th>تا مبلغ سبد (آستانه) <?php echo esc_html( $currency ); ?></th>
							<th>هزینه بسته‌بندی <?php echo esc_html( $currency ); ?></th>
							<th class="sina-packaging-tiers__actions"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tiers as $index => $tier ) : ?>
							<tr class="sina-packaging-tiers__row">
								<td>
									<input
										type="number"
										min="0"
										step="any"
										name="<?php echo esc_attr( PackagingFee::OPTION_TIERS ); ?>[<?php echo esc_attr( (string) $index ); ?>][max]"
										value="<?php echo esc_attr( (string) $tier['max'] ); ?>"
										class="sina-packaging-tiers__max"
									/>
								</td>
								<td>
									<input
										type="number"
										min="0"
										step="any"
										name="<?php echo esc_attr( PackagingFee::OPTION_TIERS ); ?>[<?php echo esc_attr( (string) $index ); ?>][fee]"
										value="<?php echo esc_attr( (string) $tier['fee'] ); ?>"
										class="sina-packaging-tiers__fee"
									/>
								</td>
								<td class="sina-packaging-tiers__actions">
									<button type="button" class="button sina-packaging-tiers__remove">حذف</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<button type="button" class="button" id="sina-packaging-tiers-add">افزودن سطح</button>
				</p>
				<p class="description">
					سطوح را از کم به زیاد وارد کنید. برای هر بازه، اگر جمع سبد «تا» آستانه باشد، هزینه همان سطح اعمال می‌شود.
				</p>
				<template id="sina-packaging-tier-template">
					<tr class="sina-packaging-tiers__row">
						<td>
							<input type="number" min="0" step="any" name="<?php echo esc_attr( PackagingFee::OPTION_TIERS ); ?>[__INDEX__][max]" value="" class="sina-packaging-tiers__max" />
						</td>
						<td>
							<input type="number" min="0" step="any" name="<?php echo esc_attr( PackagingFee::OPTION_TIERS ); ?>[__INDEX__][fee]" value="" class="sina-packaging-tiers__fee" />
						</td>
						<td class="sina-packaging-tiers__actions">
							<button type="button" class="button sina-packaging-tiers__remove">حذف</button>
						</td>
					</tr>
				</template>
			</td>
		</tr>
		<?php
	}

	public function save() {
		$settings = array_values(
			array_filter(
				$this->get_settings(),
				static function ( $setting ) {
					return ! isset( $setting['type'] ) || 'sina_packaging_tiers' !== $setting['type'];
				}
			)
		);

		\WC_Admin_Settings::save_fields( $settings );
		$this->save_tiers();

		/**
		 * Fires after packaging fee settings are saved.
		 */
		do_action( 'woocommerce_update_options_' . $this->id );
	}

	private function save_tiers() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce settings form nonce.
		$raw = isset( $_POST[ PackagingFee::OPTION_TIERS ] ) ? wp_unslash( $_POST[ PackagingFee::OPTION_TIERS ] ) : array();

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$tiers = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$max = isset( $row['max'] ) ? $row['max'] : '';
			$fee = isset( $row['fee'] ) ? $row['fee'] : '';

			if ( '' === $max && '' === $fee ) {
				continue;
			}

			$tiers[] = array(
				'max' => (float) wc_format_decimal( $max ),
				'fee' => (float) wc_format_decimal( $fee ),
			);
		}

		usort(
			$tiers,
			static function ( $a, $b ) {
				return $a['max'] <=> $b['max'];
			}
		);

		update_option( PackagingFee::OPTION_TIERS, array_values( $tiers ), false );
	}
}
