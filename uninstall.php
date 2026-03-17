<?php
/**
 * Cleanup pri odinštalácii pluginu.
 *
 * Spustí sa keď používateľ zmaže plugin cez WP Admin.
 * Zmaže DB tabuľky a options iba ak je nastavená voľba delete_on_uninstall.
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'wc_sf_settings', [] );

if ( ! empty( $settings['delete_on_uninstall'] ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-filter-manager.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-index-manager.php';

	WC_Simple_Filter\Index_Manager::uninstall();
	WC_Simple_Filter\Filter_Manager::uninstall();
}
