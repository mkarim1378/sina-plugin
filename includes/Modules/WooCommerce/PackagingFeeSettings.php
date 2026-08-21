<?php

namespace Sina_Plugin\Modules\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce submenu page for packaging fee settings.
 */
final class PackagingFeeSettings {

	const PAGE_SLUG  = 'sina-packaging';
	const NONCE_ACTION = 'sina_packaging_fee_save';
	const NONCE_NAME   = 'sina_packaging_fee_nonce';

	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 58 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_sina_packaging_fee_save', array( $this, 'handle_save' ) );
	}

	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			'آکادمی سینا - بسته‌بندی',
			'آکادمی سینا - بسته‌بندی',
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook_suffix ) {
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

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$enabled  = 'yes' === get_option( PackagingFee::OPTION_ENABLED, 'no' );
		$tiers    = PackagingFee::get_tiers();
		$currency = get_woocommerce_currency_symbol();
		$saved    = isset( $_GET['saved'] ) && '1' === $_GET['saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $tiers ) ) {
			$tiers = array(
				array(
					'max' => '',
					'fee' => '',
				),
			);
		}

		?>
		<div class="wrap sina-packaging">
			<div class="sina-packaging__shell">
				<header class="sina-packaging__hero">
					<div class="sina-packaging__hero-text">
						<p class="sina-packaging__eyebrow">ووکامرس · آکادمی سینا</p>
						<h1 class="sina-packaging__title">هزینه بسته‌بندی</h1>
						<p class="sina-packaging__lead">
							بر اساس جمع محصولات سبد، هزینه بسته‌بندی را در چند سطح تعریف کنید.
							اگر مبلغ سبد از بالاترین آستانه بیشتر باشد، هزینهٔ آخرین سطح اعمال می‌شود.
						</p>
					</div>
					<div class="sina-packaging__hero-badge" aria-hidden="true">
						<span class="sina-packaging__hero-badge-dot"></span>
						<span>پلکانی</span>
					</div>
				</header>

				<?php if ( $saved ) : ?>
					<div class="sina-packaging__notice sina-packaging__notice--success" role="status">
						تنظیمات با موفقیت ذخیره شد.
					</div>
				<?php endif; ?>

				<form
					class="sina-packaging__form"
					method="post"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				>
					<input type="hidden" name="action" value="sina_packaging_fee_save" />
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

					<section class="sina-packaging__card">
						<div class="sina-packaging__card-head">
							<div>
								<h2 class="sina-packaging__card-title">وضعیت</h2>
								<p class="sina-packaging__card-desc">فعال یا غیرفعال کردن افزودن هزینه به سبد</p>
							</div>
							<label class="sina-packaging__switch">
								<input
									type="checkbox"
									name="<?php echo esc_attr( PackagingFee::OPTION_ENABLED ); ?>"
									value="yes"
									<?php checked( $enabled ); ?>
								/>
								<span class="sina-packaging__switch-ui" aria-hidden="true"></span>
								<span class="sina-packaging__switch-label"><?php echo $enabled ? 'فعال' : 'غیرفعال'; ?></span>
							</label>
						</div>
					</section>

					<section class="sina-packaging__card">
						<div class="sina-packaging__card-head">
							<div>
								<h2 class="sina-packaging__card-title">سطوح هزینه</h2>
								<p class="sina-packaging__card-desc">
									برای هر سطح، «تا مبلغ سبد» و «هزینه بسته‌بندی» را مشخص کنید.
								</p>
							</div>
							<button type="button" class="sina-packaging__btn sina-packaging__btn--ghost" id="sina-packaging-tiers-add">
								افزودن سطح
							</button>
						</div>

						<div class="sina-packaging__tiers" id="sina-packaging-tiers">
							<?php foreach ( $tiers as $index => $tier ) : ?>
								<?php $this->render_tier_row( (int) $index, $tier, $currency ); ?>
							<?php endforeach; ?>
						</div>

						<template id="sina-packaging-tier-template">
							<?php
							$this->render_tier_row(
								'__INDEX__',
								array(
									'max' => '',
									'fee' => '',
								),
								$currency
							);
							?>
						</template>
					</section>

					<div class="sina-packaging__footer">
						<button type="submit" class="sina-packaging__btn sina-packaging__btn--primary">
							ذخیره تنظیمات
						</button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * @param int|string           $index    Row index or placeholder.
	 * @param array{max:mixed,fee:mixed} $tier Tier values.
	 * @param string               $currency Currency symbol.
	 */
	private function render_tier_row( $index, array $tier, $currency ) {
		$index_attr = (string) $index;
		?>
		<article class="sina-packaging__tier" data-tier-row>
			<div class="sina-packaging__tier-index" aria-hidden="true"></div>
			<div class="sina-packaging__tier-fields">
				<label class="sina-packaging__field">
					<span class="sina-packaging__field-label">تا مبلغ سبد</span>
					<span class="sina-packaging__field-control">
						<input
							type="number"
							min="0"
							step="any"
							inputmode="decimal"
							name="<?php echo esc_attr( PackagingFee::OPTION_TIERS ); ?>[<?php echo esc_attr( $index_attr ); ?>][max]"
							value="<?php echo esc_attr( (string) $tier['max'] ); ?>"
							placeholder="مثلاً ۵۰۰۰۰۰"
						/>
						<span class="sina-packaging__field-suffix"><?php echo esc_html( $currency ); ?></span>
					</span>
				</label>
				<span class="sina-packaging__tier-arrow" aria-hidden="true">←</span>
				<label class="sina-packaging__field">
					<span class="sina-packaging__field-label">هزینه بسته‌بندی</span>
					<span class="sina-packaging__field-control">
						<input
							type="number"
							min="0"
							step="any"
							inputmode="decimal"
							name="<?php echo esc_attr( PackagingFee::OPTION_TIERS ); ?>[<?php echo esc_attr( $index_attr ); ?>][fee]"
							value="<?php echo esc_attr( (string) $tier['fee'] ); ?>"
							placeholder="مثلاً ۲۰۰۰۰"
						/>
						<span class="sina-packaging__field-suffix"><?php echo esc_html( $currency ); ?></span>
					</span>
				</label>
			</div>
			<button type="button" class="sina-packaging__tier-remove" data-remove-tier title="حذف سطح">
				حذف
			</button>
		</article>
		<?php
	}

	public function handle_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to save these settings.', 'sina-plugin' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$enabled = isset( $_POST[ PackagingFee::OPTION_ENABLED ] ) && 'yes' === wp_unslash( $_POST[ PackagingFee::OPTION_ENABLED ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_option( PackagingFee::OPTION_ENABLED, $enabled ? 'yes' : 'no', false );

		$raw = isset( $_POST[ PackagingFee::OPTION_TIERS ] ) ? wp_unslash( $_POST[ PackagingFee::OPTION_TIERS ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

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

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => self::PAGE_SLUG,
					'saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
