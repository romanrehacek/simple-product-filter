<?php
/**
 * Plugin Name: WC Simple Filter
 * Plugin URI:  https://github.com/romanrehacek/wc-simple-filter
 * Description: Configurable product filters for WooCommerce shop. Insert via shortcode [wc_simple_filter] or PHP function wc_simple_filter().
 * Version:     0.1.0
 * Author:      Roman Rehacek
 * Author URI:  https://github.com/romanrehacek
 * Text Domain: wc-simple-filter
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * WC tested up to:      9.x
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_SF_VERSION', '0.1.0' );
define( 'WC_SF_PLUGIN_FILE', __FILE__ );
define( 'WC_SF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_SF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_SF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active.
 */
function wc_sf_is_woocommerce_active(): bool {
	return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active.
 */
function wc_sf_woocommerce_missing_notice(): void {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'WC Simple Filter requires an active WooCommerce plugin.', 'wc-simple-filter' );
	echo '</p></div>';
}

/**
 * Main plugin initialization.
 */
function wc_sf_init(): void {
	if ( ! wc_sf_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'wc_sf_woocommerce_missing_notice' );
		return;
	}

	require_once WC_SF_PLUGIN_DIR . 'includes/class-plugin.php';
	WC_Simple_Filter\Plugin::get_instance()->init();
}

add_action( 'plugins_loaded', 'wc_sf_init' );

/**
 * Declare compatibility with WooCommerce features (HPOS, Cart/Checkout Blocks).
 */
add_action(
	'before_woocommerce_init',
	function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * Activation hook — creates database tables.
 */
function wc_sf_activate(): void {
	if ( ! wc_sf_is_woocommerce_active() ) {
		deactivate_plugins( WC_SF_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'WC Simple Filter requires an active WooCommerce plugin.', 'wc-simple-filter' ),
			esc_html__( 'Activation Error', 'wc-simple-filter' ),
			[ 'back_link' => true ]
		);
	}

	require_once WC_SF_PLUGIN_DIR . 'includes/class-filter-manager.php';
	WC_Simple_Filter\Filter_Manager::install();
}

register_activation_hook( __FILE__, 'wc_sf_activate' );

/**
 * Deactivation hook.
 */
function wc_sf_deactivate(): void {
	// Tables remain. No data changes on deactivation.
}

register_deactivation_hook( __FILE__, 'wc_sf_deactivate' );

/**
 * PHP helper function for inserting filters into template.
 *
 * @param array<string, mixed> $args Arguments (reserved for Phase 2).
 */
function wc_simple_filter( array $args = [] ): void {
	// Phase 2 — frontend rendering.
	if ( ! wc_sf_is_woocommerce_active() ) {
		return;
	}
	do_action( 'wc_sf_render_filters', $args );
}
