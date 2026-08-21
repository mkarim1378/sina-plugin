<?php
/**
 * Plugin Name: سینا آکادمی
 * Plugin URI: https://sina-academy.ir
 * Description: افزونه اختصاصی برای افزودن کدهای دلخواه سایت سینا خسروی (آموزش تعمیرات آیفون تصویری).
 * Version: 1.0.0
 * Author: محمد کریم قصبه
 * Author URI: https://m-karim.ir
 * License: GPL2
 * Text Domain: sina-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function keep_me_logged_in_for_longer( $expirein ) {
    return 12 * 60 * 60 * 24 * 30; // 30 روز لاگین بمونه
}
add_filter( 'auth_cookie_expiration', 'keep_me_logged_in_for_longer' );

// Disable Gutenberg
add_filter( 'use_block_editor_for_post', '__return_false' );
add_filter( 'use_widgets_block_editor', '__return_false' );
add_action( 'wp_enqueue_scripts', function() {
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'global-styles' );
}, 20 );

// disable auto update for translates
add_filter( 'auto_update_translation', '__return_false' );
add_filter( 'async_update_translation', '__return_false' );

// for selecting product variation
add_shortcode( 'variation_price_by_attr', function( $atts ) {
    global $product;

    // اگر هنوز محصول ست نشده باشه، از get_the_ID بگیریم
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
        $product_id = get_the_ID();
        $product    = wc_get_product( $product_id );
    }

    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        return '';
    }

    // ویژگی‌ها رو از شورتکد می‌گیریم
    $attributes = $atts;
    if ( empty( $attributes ) ) return '';

    $data_store   = WC_Data_Store::load( 'product' );
    $variation_id = $data_store->find_matching_product_variation( $product, $attributes );

    if ( $variation_id ) {
        $variation = wc_get_product( $variation_id );
        if ( $variation ) {
            return $variation->get_price_html();
        }
    }

    return '';
});


add_filter( 'woocommerce_checkout_fields', 'customize_checkout_fields_strict_check' );

function customize_checkout_fields_strict_check( $fields ) {
    $target_category_ids = array( 25, 26, 27 );
    
    // فرض اولیه: فقط محصولات خاص در سبد هستند
    $all_items_are_special = true;

    foreach ( WC()->cart->get_cart() as $cart_item ) {
        // اگر محصولی پیدا شد که جزو دسته های هدف نبود
        if ( ! has_term( $target_category_ids, 'product_cat', $cart_item['data']->get_id() ) ) {
            $all_items_are_special = false;
            break; // حلقه را قطع کن چون فهمیدیم باید آدرس بگیریم
        }
    }

    if ( $all_items_are_special ) {
        // حالت اول: فقط محصولات خاص در سبد هستند (حذف آدرس)
        $allowed_fields = array( 'billing_first_name', 'billing_last_name', 'billing_phone' );

        foreach ( $fields['billing'] as $key => $field ) {
            if ( ! in_array( $key, $allowed_fields ) ) {
                unset( $fields['billing'][ $key ] );
            } else {
                $fields['billing'][ $key ]['required'] = true;
            }
        }

        unset( $fields['shipping'] );
        unset( $fields['order']['order_comments'] );

    } else {
        // حالت دوم: حداقل یک محصول معمولی در سبد هست (نمایش آدرس)
        unset( $fields['billing']['billing_address_2'] );
        
        $fields['billing']['billing_address_1']['label'] = 'آدرس پستی';
        $fields['billing']['billing_address_1']['placeholder'] = 'آدرس کامل پستی خود را بنویسید';
        $fields['billing']['billing_address_1']['required'] = true;

        $fields['billing']['billing_first_name']['required'] = true;
        $fields['billing']['billing_last_name']['required'] = true;
        $fields['billing']['billing_phone']['required'] = true;

        unset( $fields['billing']['billing_country'] );
//         unset( $fields['billing']['billing_city'] );
//         unset( $fields['billing']['billing_state'] );
        unset( $fields['billing']['billing_email'] );
        unset( $fields['order']['order_comments'] );
//         unset( $fields['billing']['billing_postcode'] );
    }

    return $fields;
}

add_filter( 'woocommerce_billing_fields', 'make_email_optional_fix', 10, 1 );

function make_email_optional_fix( $fields ) {
    if ( isset( $fields['billing_email'] ) ) {
        $fields['billing_email']['required'] = false;
    }
    return $fields;
}