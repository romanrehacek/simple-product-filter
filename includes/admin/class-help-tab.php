<?php
/**
 * Záložka „Nápoveda" — dokumentácia pre vývojára.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trieda Help_Tab.
 */
class Help_Tab {

	/**
	 * Renderuje obsah záložky.
	 *
	 * @return void
	 */
	public function render(): void {
		include WC_SF_PLUGIN_DIR . 'templates/admin/help-tab.php';
	}
}
