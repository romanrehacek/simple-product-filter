<?php
/**
 * Záložka „Filtre" — zoznam filtrov s repeaterom.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Simple_Filter\Filter_Manager;

/**
 * Trieda Filters_Tab.
 */
class Filters_Tab {

	/**
	 * Renderuje obsah záložky.
	 *
	 * @return void
	 */
	public function render(): void {
		$filters = Filter_Manager::get_all();
		include WC_SF_PLUGIN_DIR . 'templates/admin/filters-tab.php';
	}
}
