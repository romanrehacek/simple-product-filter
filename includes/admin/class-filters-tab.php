<?php
/**
 * "Filters" tab — list of filters with repeater.
 *
 * @package Simple_Product_Filter
 */

namespace Simple_Product_Filter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Simple_Product_Filter\Filter_Manager;

/**
 * Filters_Tab class.
 */
class Filters_Tab {

	/**
	 * Renders the tab content.
	 *
	 * @return void
	 */
	public function render(): void {
		$filters = Filter_Manager::get_all();
		include SPF_PLUGIN_DIR . 'templates/admin/filters-tab.php';
	}
}
