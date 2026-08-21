<?php

namespace Sina_Plugin\Modules;

use Sina_Plugin\Contracts\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disables the block editor and related front-end assets.
 */
final class Editor implements ModuleInterface {

	public function is_enabled() {
		return true;
	}

	public function register() {
		add_filter( 'use_block_editor_for_post', '__return_false' );
		add_filter( 'use_widgets_block_editor', '__return_false' );
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_block_styles' ), 20 );
	}

	public function dequeue_block_styles() {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'global-styles' );
	}
}
