<?php
/**
 * Stránka editácie konkrétneho filtra.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Simple_Filter\Filter_Manager;

/**
 * Trieda Filter_Edit.
 */
class Filter_Edit {

	/**
	 * Renderuje editačný formulár pre daný filter.
	 *
	 * @param int $filter_id ID filtra (0 = nový filter, no aktuálne len editácia).
	 * @return void
	 */
	public function render( int $filter_id ): void {
		if ( $filter_id <= 0 ) {
			wp_safe_redirect( Admin::tab_url() );
			exit;
		}

		$filter = Filter_Manager::get( $filter_id );

		if ( ! $filter ) {
			wp_die(
				esc_html__( 'Filter nebol nájdený.', 'wc-simple-filter' ),
				esc_html__( 'Chyba', 'wc-simple-filter' ),
				[ 'back_link' => true ]
			);
		}

		include WC_SF_PLUGIN_DIR . 'templates/admin/filter-edit.php';
	}
}
