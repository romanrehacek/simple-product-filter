<?php
/**
 * "Settings" tab — general plugin settings.
 *
 * @package Simple_Product_Filter
 */

namespace Simple_Product_Filter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Simple_Product_Filter\Filter_Manager;
use Simple_Product_Filter\Index_Manager;

/**
 * Settings_Tab class.
 */
class Settings_Tab {

	/**
	 * Renders the tab content.
	 *
	 * @return void
	 */
	public function render(): void {
		$settings   = get_option( 'spf_settings', Filter_Manager::default_settings() );
		$index_time = $this->get_last_index_time();
		include SPF_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}

	/**
	 * Returns the time of the last index update.
	 *
	 * @return string|null
	 */
	private function get_last_index_time(): ?string {
		global $wpdb;

		$cache_key = 'spf_last_index_time';
		$cached    = wp_cache_get( $cache_key );

		if ( false !== $cached ) {
			return 'null' === $cached ? null : $cached;
		}

		$table = $wpdb->prefix . Index_Manager::TABLE_INDEX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$time  = $wpdb->get_var( "SELECT MAX(updated_at) FROM {$table}" );

		if ( null === $time ) {
			wp_cache_set( $cache_key, 'null', '', 3600 );
		} else {
			wp_cache_set( $cache_key, $time, '', 3600 );
		}

		return $time ?: null;
	}
}
