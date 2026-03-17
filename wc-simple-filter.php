<?php
/**
 * Plugin Name: WC Simple Filter
 * Plugin URI:  https://github.com/sjbdigital/wc-simple-filter
 * Description: Konfigurovateľné produktové filtre pre WooCommerce obchod. Vložte cez shortcode [wc_simple_filter] alebo PHP funkciu wc_simple_filter().
 * Version:     0.1.0
 * Author:      SJB Digital
 * Author URI:  https://sjbdigital.dev
 * Text Domain: wc-simple-filter
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * WC requires at least: 7.0
 * WC tested up to:      9.x
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
 * Kontrola či je WooCommerce aktívny.
 */
function wc_sf_is_woocommerce_active(): bool {
	return class_exists( 'WooCommerce' );
}

/**
 * Zobrazí admin notice ak WooCommerce nie je aktívny.
 */
function wc_sf_woocommerce_missing_notice(): void {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'WC Simple Filter vyžaduje aktívny plugin WooCommerce.', 'wc-simple-filter' );
	echo '</p></div>';
}

/**
 * Hlavná inicializácia pluginu.
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
 * Aktivačný hook — vytvorí DB tabuľky.
 */
function wc_sf_activate(): void {
	if ( ! wc_sf_is_woocommerce_active() ) {
		deactivate_plugins( WC_SF_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'WC Simple Filter vyžaduje aktívny plugin WooCommerce.', 'wc-simple-filter' ),
			esc_html__( 'Chyba aktivácie', 'wc-simple-filter' ),
			[ 'back_link' => true ]
		);
	}

	require_once WC_SF_PLUGIN_DIR . 'includes/class-filter-manager.php';
	WC_Simple_Filter\Filter_Manager::install();
}

register_activation_hook( __FILE__, 'wc_sf_activate' );

/**
 * Deaktivačný hook.
 */
function wc_sf_deactivate(): void {
	// Tabuľky zostávajú. Žiadne dátové zmeny pri deaktivácii.
}

register_deactivation_hook( __FILE__, 'wc_sf_deactivate' );

/**
 * PHP helper funkcia pre vloženie filtrov do šablóny.
 *
 * @param array<string, mixed> $args Argumenty (zatiaľ rezervované pre Fázu 2).
 */
function wc_simple_filter( array $args = [] ): void {
	// Fáza 2 — frontend rendering.
	if ( ! wc_sf_is_woocommerce_active() ) {
		return;
	}
	do_action( 'wc_sf_render_filters', $args );
}
