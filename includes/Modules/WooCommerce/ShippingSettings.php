<?php

namespace Sina_Plugin\Modules\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce submenu page for shipping and packaging settings.
 */
final class ShippingSettings {

	const PAGE_SLUG    = 'sina-packaging';
	const NONCE_ACTION = 'sina_shipping_save';
	const NONCE_NAME   = 'sina_shipping_nonce';

	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 58 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_sina_shipping_save', array( $this, 'handle_save' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SINA_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
	}

	/**
	 * @param array<string, string> $links Plugin row action links.
	 * @return array<string, string>
	 */
	public function add_settings_link( $links ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $links;
		}

		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );

		$links = array_merge(
			array(
				'settings' => '<a href="' . esc_url( $url ) . '">تنظیمات</a>',
			),
			$links
		);

		return $links;
	}

	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			'آکادمی سینا - ارسال',
			'آکادمی سینا - ارسال',
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

		$methods  = Shipping::get_methods();
		$currency = get_woocommerce_currency_symbol();
		$saved    = isset( $_GET['saved'] ) && '1' === $_GET['saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wrap sina-packaging">
			<div class="sina-packaging__shell">
				<header class="sina-packaging__hero">
					<div class="sina-packaging__hero-text">
						<p class="sina-packaging__eyebrow">ووکامرس · آکادمی سینا</p>
						<h1 class="sina-packaging__title">ارسال و بسته‌بندی</h1>
						<p class="sina-packaging__lead">
							روش‌های ارسال را همین‌جا مدیریت کنید؛ بدون نیاز به تنظیمات حمل ووکامرس.
							هر روش فعال در تسویه حساب نمایش داده می‌شود و هزینه بسته‌بندی همان روش به سبد اضافه می‌شود.
						</p>
					</div>
					<div class="sina-packaging__hero-badge" aria-hidden="true">
						<span class="sina-packaging__hero-badge-dot"></span>
						<span>مستقل از ووکامرس</span>
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
					<input type="hidden" name="action" value="sina_shipping_save" />
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

					<section class="sina-packaging__methods" aria-label="روش‌های ارسال">
						<?php foreach ( $methods as $key => $method ) : ?>
							<?php $this->render_method_card( $key, $method, $currency ); ?>
						<?php endforeach; ?>
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
	 * @param string                                                                                              $key      Method key.
	 * @param array{label: string, enabled: bool, cost_type: string, shipping_cost: float, packaging_cost: float} $method   Method values.
	 * @param string                                                                                              $currency Currency symbol.
	 */
	private function render_method_card( $key, array $method, $currency ) {
		$is_pickup = Shipping::PICKUP_KEY === $key;
		$enabled   = ! empty( $method['enabled'] );
		$cost_type = Shipping::normalize_cost_type( $method['cost_type'], $key );
		$base      = Shipping::OPTION_METHODS . '[' . $key . ']';
		?>
		<article
			class="sina-packaging__method<?php echo $enabled ? '' : ' is-disabled'; ?>"
			data-shipping-method
		>
			<div class="sina-packaging__card-head">
				<div>
					<h2 class="sina-packaging__card-title"><?php echo esc_html( $method['label'] ); ?></h2>
					<p class="sina-packaging__card-desc">
						<?php
						echo $is_pickup
							? 'هزینه ارسال این گزینه همیشه رایگان است.'
							: 'نوع هزینه ارسال را انتخاب کنید؛ بسته‌بندی جداگانه تنظیم می‌شود.';
						?>
					</p>
				</div>
				<label class="sina-packaging__switch">
					<input
						type="checkbox"
						name="<?php echo esc_attr( $base ); ?>[enabled]"
						value="yes"
						<?php checked( $enabled ); ?>
					/>
					<span class="sina-packaging__switch-ui" aria-hidden="true"></span>
					<span class="sina-packaging__switch-label"><?php echo $enabled ? 'فعال' : 'غیرفعال'; ?></span>
				</label>
			</div>

			<div class="sina-packaging__costs">
				<div class="sina-packaging__field sina-packaging__field--shipping">
					<span class="sina-packaging__field-label">هزینه ارسال</span>
					<?php if ( $is_pickup ) : ?>
						<span class="sina-packaging__free">رایگان</span>
					<?php else : ?>
						<div class="sina-packaging__cost-types" role="radiogroup" aria-label="نوع هزینه ارسال">
							<?php foreach ( Shipping::COST_TYPE_LABELS as $type => $type_label ) : ?>
								<label class="sina-packaging__cost-type">
									<input
										type="radio"
										name="<?php echo esc_attr( $base ); ?>[cost_type]"
										value="<?php echo esc_attr( $type ); ?>"
										<?php checked( $cost_type, $type ); ?>
										data-cost-type
									/>
									<span><?php echo esc_html( $type_label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<span
							class="sina-packaging__field-control sina-packaging__amount<?php echo Shipping::COST_TYPE_AMOUNT === $cost_type ? '' : ' is-hidden'; ?>"
							data-amount-field
						>
							<input
								type="number"
								min="0"
								step="any"
								inputmode="decimal"
								name="<?php echo esc_attr( $base ); ?>[shipping_cost]"
								value="<?php echo esc_attr( $this->format_amount( $method['shipping_cost'] ) ); ?>"
								placeholder="مثلاً ۵۰۰۰۰"
							/>
							<span class="sina-packaging__field-suffix"><?php echo esc_html( $currency ); ?></span>
						</span>
					<?php endif; ?>
				</div>
				<label class="sina-packaging__field">
					<span class="sina-packaging__field-label">هزینه بسته‌بندی</span>
					<span class="sina-packaging__field-control">
						<input
							type="number"
							min="0"
							step="any"
							inputmode="decimal"
							name="<?php echo esc_attr( $base ); ?>[packaging_cost]"
							value="<?php echo esc_attr( $this->format_amount( $method['packaging_cost'] ) ); ?>"
							placeholder="مثلاً ۲۰۰۰۰"
						/>
						<span class="sina-packaging__field-suffix"><?php echo esc_html( $currency ); ?></span>
					</span>
				</label>
			</div>
		</article>
		<?php
	}

	/**
	 * @param float $amount Amount.
	 * @return string
	 */
	private function format_amount( $amount ) {
		if ( (float) $amount <= 0 ) {
			return '';
		}

		return (string) $amount;
	}

	public function handle_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to save these settings.', 'sina-plugin' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$raw = isset( $_POST[ Shipping::OPTION_METHODS ] ) ? wp_unslash( $_POST[ Shipping::OPTION_METHODS ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$methods = array();

		foreach ( array_keys( Shipping::METHOD_LABELS ) as $key ) {
			$row = isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ? $raw[ $key ] : array();

			$packaging = isset( $row['packaging_cost'] ) ? $row['packaging_cost'] : '';
			$shipping  = isset( $row['shipping_cost'] ) ? $row['shipping_cost'] : '';
			$cost_type = isset( $row['cost_type'] ) ? sanitize_key( (string) $row['cost_type'] ) : Shipping::COST_TYPE_FREE;
			$cost_type = Shipping::normalize_cost_type( $cost_type, $key );
			$amount    = Shipping::PICKUP_KEY === $key ? 0.0 : (float) wc_format_decimal( $shipping );

			if ( Shipping::COST_TYPE_AMOUNT !== $cost_type ) {
				$amount = 0.0;
			}

			$methods[ $key ] = array(
				'enabled'        => isset( $row['enabled'] ) && 'yes' === $row['enabled'],
				'cost_type'      => $cost_type,
				'shipping_cost'  => $amount,
				'packaging_cost' => (float) wc_format_decimal( $packaging ),
			);
		}

		update_option( Shipping::OPTION_METHODS, $methods, false );

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
