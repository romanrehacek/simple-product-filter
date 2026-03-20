<?php
/**
 * "Help" tab — developer documentation.
 *
 * @package Simple_Product_Filter
 */

namespace Simple_Product_Filter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Help_Tab class.
 */
class Help_Tab {

	/**
	 * Renders the tab content.
	 *
	 * @return void
	 */
	public function render(): void {
		include SPF_PLUGIN_DIR . 'templates/admin/help-tab.php';
	}
}
