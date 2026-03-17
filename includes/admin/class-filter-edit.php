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

		$price_range = ( 'price' === $filter['filter_type'] ) ? self::get_price_range() : null;

		include WC_SF_PLUGIN_DIR . 'templates/admin/filter-edit.php';
	}

	/**
	 * Vráti min a max cenu publikovaných produktov.
	 *
	 * @return array{min: float, max: float}|null  null ak nie sú žiadne produkty.
	 */
	private static function get_price_range(): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			"SELECT MIN( CAST( meta_value AS DECIMAL(15,4) ) ) AS min_price,
			        MAX( CAST( meta_value AS DECIMAL(15,4) ) ) AS max_price
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key   = '_price'
			   AND pm.meta_value != ''
			   AND p.post_type   = 'product'
			   AND p.post_status = 'publish'",
			ARRAY_A
		);

		if ( ! $row || null === $row['min_price'] ) {
			return null;
		}

		return [
			'min' => (float) $row['min_price'],
			'max' => (float) $row['max_price'],
		];
	}
}
