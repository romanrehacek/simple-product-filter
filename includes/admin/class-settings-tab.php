<?php
/**
 * Záložka „Nastavenia" — všeobecné nastavenia pluginu.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Simple_Filter\Filter_Manager;
use WC_Simple_Filter\Index_Manager;

/**
 * Trieda Settings_Tab.
 */
class Settings_Tab {

	/**
	 * Renderuje obsah záložky.
	 *
	 * @return void
	 */
	public function render(): void {
		$settings   = get_option( 'wc_sf_settings', Filter_Manager::default_settings() );
		$index_time = $this->get_last_index_time();
		include WC_SF_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}

	/**
	 * Vráti čas poslednej aktualizácie indexu.
	 *
	 * @return string|null
	 */
	private function get_last_index_time(): ?string {
		global $wpdb;

		$table = $wpdb->prefix . Index_Manager::TABLE_INDEX;
		$time  = $wpdb->get_var( "SELECT MAX(updated_at) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $time ?: null;
	}
}
