<?php
/**
 * Frontend class — enqueue CSS/JS and register frontend hooks.
 *
 * @package Simple_Product_Filter
 */

namespace Simple_Product_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend class.
 *
 * Responsible for:
 * - enqueuing frontend CSS and JS
 * - localizing JS data
 * - registering frontend hooks
 */
class Frontend {

	/**
	 * Initializes frontend hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'pre_get_posts',      [ $this, 'filter_main_query' ] );
	}

	/**
	 * Applies filter parameters to the main WooCommerce product query.
	 *
	 * Runs only when:
	 *  - we are not in admin
	 *  - it is the main query (not a secondary one, e.g. widgets)
	 *  - it is a WC product archive, shop page, or product taxonomy
	 *  - hook spf_apply_query_filter returns true
	 *
	 * Pagination is handled exclusively in JS (reset paged=1 when filters change).
	 * PHP only reads $_GET['wcsf'] — if the parameter is not in the URL, the query does not change.
	 *
	 * @param  \WP_Query $query  Current WP_Query object (pass by reference).
	 * @return void
	 */
	public function filter_main_query( \WP_Query $query ): void {
		// Run only on the frontend and on the main query.
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Run only on WC product archives / shop / product taxonomies.
		if ( ! $this->is_filterable_query( $query ) ) {
			return;
		}

		/**
		 * Filter allowing you to disable filtering for a specific query.
		 *
		 * @param bool      $apply  Apply filtering? Default true.
		 * @param \WP_Query $query  Current WP_Query object.
		 */
		if ( ! (bool) apply_filters( 'spf_apply_query_filter', true, $query ) ) {
			return;
		}

		// Load and sanitize parameters from the URL.
		$params = Query_Builder::get_active_params();

		if ( empty( $params ) ) {
			return;
		}

		// Load filter configurations for correct AND/OR logic.
		$filter_configs = Filter_Manager::get_all();

		// Build WP_Query args fragment.
		$extra = Query_Builder::build( $params, $filter_configs );

		if ( empty( $extra ) ) {
			return;
		}

		/**
		 * Action before applying filter conditions to the query.
		 *
		 * @param \WP_Query             $query  WP_Query object.
		 * @param array<string, mixed>  $extra  Built query args.
		 * @param array<string, mixed>  $params Active filter parameters.
		 */
		do_action( 'spf_before_filter_query', $query, $extra, $params );

		// --- tax_query ---
		if ( ! empty( $extra['tax_query'] ) ) {
			$existing = $query->get( 'tax_query' ) ?: [];
			// Keep existing conditions (e.g. WC category archive).
			if ( ! empty( $existing ) ) {
				$merged = [
					'relation' => 'AND',
					$existing,
					$extra['tax_query'],
				];
			} else {
				$merged = $extra['tax_query'];
			}
			$query->set( 'tax_query', $merged ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		// --- meta_query ---
		if ( ! empty( $extra['meta_query'] ) ) {
			$existing = $query->get( 'meta_query' ) ?: [];
			if ( ! empty( $existing ) ) {
				$merged = [
					'relation' => 'AND',
					$existing,
					$extra['meta_query'],
				];
			} else {
				$merged = $extra['meta_query'];
			}
			$query->set( 'meta_query', $merged ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		// --- post__in (sale filter) ---
		if ( isset( $extra['post__in'] ) ) {
			$existing = $query->get( 'post__in' ) ?: [];
			if ( ! empty( $existing ) ) {
				// Intersection — both conditions must be met.
				$intersected = array_intersect( $existing, $extra['post__in'] );
				$query->set( 'post__in', ! empty( $intersected ) ? array_values( $intersected ) : [ 0 ] );
			} else {
				$query->set( 'post__in', $extra['post__in'] );
			}
		}
	}

	/**
	 * Checks whether the current query is a filterable WC product query.
	 *
	 * @param  \WP_Query $query  WP_Query object.
	 * @return bool
	 */
	private function is_filterable_query( \WP_Query $query ): bool {
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return true;
		}

		if ( $query->is_post_type_archive( 'product' ) ) {
			return true;
		}

		// Product taxonomies (category, tag, attributes...).
		$product_taxonomies = get_object_taxonomies( 'product' );
		if ( ! empty( $product_taxonomies ) && $query->is_tax( $product_taxonomies ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueue frontend CSS and JS.
	 * Loaded only on frontend pages (not in admin).
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Enqueue only if shortcode is on the page or on shop/archive page.
		if ( ! $this->should_enqueue() ) {
			return;
		}

		// Frontend CSS.
		wp_enqueue_style(
			'spf-frontend',
			SPF_PLUGIN_URL . 'assets/css/frontend.css',
			[],
			SPF_VERSION
		);

		// jQuery UI Slider (built-in in WP — doesn't need external CDN).
		wp_enqueue_script( 'jquery-ui-slider' );

		// Frontend JS.
		wp_enqueue_script(
			'spf-frontend',
			SPF_PLUGIN_URL . 'assets/js/frontend.js',
			[ 'jquery', 'jquery-ui-slider' ],
			SPF_VERSION,
			true
		);

		// Localization of JS data.
		wp_localize_script(
			'spf-frontend',
			'spfFrontend',
			$this->get_js_data()
		);
	}

	/**
	 * Returns an array of data for JS localization.
	 *
	 * @return array<string, mixed>
	 */
	private function get_js_data(): array {
		$settings = get_option( 'spf_settings', [] );

		return [
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'spf_frontend_nonce' ),
			'filterMode'     => $settings['filter_mode'] ?? 'ajax',
			'i18n'           => [
				'viewMore'     => __( 'Show more', 'simple-product-filter' ),
				'viewLess'     => __( 'Show less', 'simple-product-filter' ),
				'resetAll'     => $settings['reset_button_text'] ?? __( 'Reset filters', 'simple-product-filter' ),
				'closeLabel'   => __( 'Close', 'simple-product-filter' ),
				'removeFilter' => __( 'Remove filter', 'simple-product-filter' ),
			],
		];
	}

	/**
	 * Checks whether assets should be loaded on the current page.
	 * Always loads — shortcode can appear anywhere.
	 * Performance is handled through conditional loading in the future.
	 *
	 * @return bool
	 */
	private function should_enqueue(): bool {
		/**
		 * Filter to control whether to load frontend assets.
		 *
		 * @param bool $should Load assets? Default true.
		 */
		return (bool) apply_filters( 'spf_enqueue_frontend_assets', true );
	}
}
