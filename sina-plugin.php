<?php
/**
 * Plugin Name: سینا آکادمی
 * Plugin URI: https://sina-academy.ir
 * Description: افزونه اختصاصی برای افزودن کدهای دلخواه سایت سینا خسروی (آموزش تعمیرات آیفون تصویری).
 * Version: 1.5.0
 * Author: محمد کریم قصبه
 * Author URI: https://m-karim.ir
 * License: GPL2
 * Text Domain: sina-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SINA_PLUGIN_VERSION', '1.5.0' );
define( 'SINA_PLUGIN_FILE', __FILE__ );
define( 'SINA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SINA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SINA_PLUGIN_PATH . 'includes/Autoloader.php';

Sina_Plugin\Autoloader::register( SINA_PLUGIN_PATH . 'includes' );

add_action(
	'plugins_loaded',
	static function () {
		Sina_Plugin\Plugin::instance()->boot();
	}
);
