<?php
/**
 * Individual filter edit page.
 *
 * @package Simple_Product_Filter
 */

namespace Simple_Product_Filter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Simple_Product_Filter\Filter_Manager;

/**
 * Filter_Edit class.
 */
class Filter_Edit {

	/**
	 * Renders the edit form for a given filter.
	 *
	 * @param int $filter_id Filter ID (0 = new filter, currently only editing).
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
				esc_html__( 'Filter not found.', 'simple-product-filter' ),
				esc_html__( 'Error', 'simple-product-filter' ),
				[ 'back_link' => true ]
			);
		}

		$price_range      = ( 'price' === $filter['filter_type'] ) ? self::get_price_range() : null;
		$available_values = self::load_available_values( $filter['filter_type'] );

		include SPF_PLUGIN_DIR . 'templates/admin/filter-edit.php';
	}

	/**
	 * Loads available values for a given filter_type.
	 *
	 * Returns an empty array for types where value selection is not relevant
	 * (price, status, fixed styles).
	 *
	 * @param string $filter_type Filter type.
	 * @return array<int, array<string, mixed>>
	 */
	private static function load_available_values( string $filter_type ): array {
		if ( 'brand' === $filter_type ) {
			return self::get_term_values( 'product_brand' );
		}

		if ( str_starts_with( $filter_type, 'attribute_' ) ) {
			$taxonomy = substr( $filter_type, strlen( 'attribute_' ) );
			return self::get_term_values( $taxonomy );
		}

		if ( str_starts_with( $filter_type, 'meta_' ) ) {
			$meta_key = substr( $filter_type, strlen( 'meta_' ) );
			return self::get_meta_values( $meta_key );
		}

		// For price, status, sale — value selection is not handled here.
		return [];
	}

	/**
	 * Loads terms from a taxonomy as value/label/count array.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_term_values( string $taxonomy ): array {
		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		$values = [];

		foreach ( $terms as $term ) {
			$values[] = [
				'value' => $term->slug,
				'label' => $term->name,
				'count' => (int) $term->count,
			];
		}

		return $values;
	}

	/**
	 * Loads unique values for a meta key from products.
	 *
	 * @param string $meta_key Meta key.
	 * @return array<int, array<string, string>>
	 */
	private static function get_meta_values( string $meta_key ): array {
		global $wpdb;

		// Try cache first.
		$cache_key = 'spf_meta_values_' . md5( $meta_key );
		$rows      = wp_cache_get( $cache_key );

		if ( false === $rows ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT pm.meta_value as value
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE pm.meta_key = %s
					AND pm.meta_value != ''
					AND p.post_type = 'product'
					AND p.post_status = 'publish'
					ORDER BY pm.meta_value ASC",
					$meta_key
				),
				ARRAY_A
			);

			if ( is_array( $rows ) ) {
				wp_cache_set( $cache_key, $rows, '', 3600 );
			} else {
				$rows = [];
			}
		}

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map( fn( $row ) => [
			'value' => $row['value'],
			'label' => $row['value'],
		], $rows );
	}

	/**
	 * Returns min and max price of published products.
	 *
	 * @return array{min: float, max: float}|null  null if no products exist.
	 */
	private static function get_price_range(): ?array {
		global $wpdb;

		// Try cache first.
		$cache_key = 'spf_price_range';
		$cached    = wp_cache_get( $cache_key );

		if ( false !== $cached ) {
			return 'null' === $cached ? null : $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
			wp_cache_set( $cache_key, 'null', '', 3600 );
			return null;
		}

		$result = [
			'min' => (float) $row['min_price'],
			'max' => (float) $row['max_price'],
		];

		wp_cache_set( $cache_key, $result, '', 3600 );
		return $result;
	}
}
