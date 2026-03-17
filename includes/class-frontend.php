<?php
/**
 * Frontend trieda — enqueue CSS/JS a registrácia frontend hookov.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trieda Frontend.
 *
 * Zodpovedá za:
 * - enqueue frontend CSS a JS
 * - lokalizáciu JS dát
 * - registráciu frontend hookov
 */
class Frontend {

	/**
	 * Inicializuje frontend hooky.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'pre_get_posts',      [ $this, 'filter_main_query' ] );
	}

	/**
	 * Aplikuje filter parametre na hlavný WooCommerce product query.
	 *
	 * Spustí sa iba keď:
	 *  - nie sme v admin
	 *  - je to hlavný query (nie vedľajší, napr. widgety)
	 *  - je to WC product archive, shop stránka alebo product taxonomy
	 *  - hook wc_sf_apply_query_filter vracia true
	 *
	 * Stránkovanie sa rieši výhradne v JS (reset paged=1 pri zmene filtrov).
	 * PHP len číta $_GET['wcsf'] — ak parameter nie je v URL, query sa nezmení.
	 *
	 * @param  \WP_Query $query  Aktuálny WP_Query objekt (pass by reference).
	 * @return void
	 */
	public function filter_main_query( \WP_Query $query ): void {
		// Spustiť iba na fronte a na hlavnom query.
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Spustiť iba na WC product archívoch / shop / product taxonomiách.
		if ( ! $this->is_filterable_query( $query ) ) {
			return;
		}

		/**
		 * Filter umožňujúci vypnúť filtrovanie pre konkrétny query.
		 *
		 * @param bool      $apply  Aplikovať filtrovanie? Default true.
		 * @param \WP_Query $query  Aktuálny WP_Query objekt.
		 */
		if ( ! (bool) apply_filters( 'wc_sf_apply_query_filter', true, $query ) ) {
			return;
		}

		// Načítaj a sanitizuj parametre z URL.
		$params = Query_Builder::get_active_params();

		if ( empty( $params ) ) {
			return;
		}

		// Načítaj konfigurácie filtrov pre správnu AND/OR logiku.
		$filter_configs = Filter_Manager::get_all();

		// Zostrojí WP_Query args fragment.
		$extra = Query_Builder::build( $params, $filter_configs );

		if ( empty( $extra ) ) {
			return;
		}

		/**
		 * Akcia pred aplikovaním filtrovacích podmienok na query.
		 *
		 * @param \WP_Query             $query  WP_Query objekt.
		 * @param array<string, mixed>  $extra  Zostavené query args.
		 * @param array<string, mixed>  $params Aktívne filter parametre.
		 */
		do_action( 'wc_sf_before_filter_query', $query, $extra, $params );

		// --- tax_query ---
		if ( ! empty( $extra['tax_query'] ) ) {
			$existing = $query->get( 'tax_query' ) ?: [];
			// Zachovaj existujúce podmienky (napr. WC category archive).
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
				// Prienik — obe podmienky musia byť splnené.
				$intersected = array_intersect( $existing, $extra['post__in'] );
				$query->set( 'post__in', ! empty( $intersected ) ? array_values( $intersected ) : [ 0 ] );
			} else {
				$query->set( 'post__in', $extra['post__in'] );
			}
		}
	}

	/**
	 * Skontroluje, či aktuálny query je filtrovateľný WC product query.
	 *
	 * @param  \WP_Query $query  WP_Query objekt.
	 * @return bool
	 */
	private function is_filterable_query( \WP_Query $query ): bool {
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return true;
		}

		if ( $query->is_post_type_archive( 'product' ) ) {
			return true;
		}

		// Product taxonomies (kategória, tag, atribúty...).
		$product_taxonomies = get_object_taxonomies( 'product' );
		if ( ! empty( $product_taxonomies ) && $query->is_tax( $product_taxonomies ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueue frontend CSS a JS.
	 * Načíta sa iba na frontend stránkach (nie v admin).
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Enqueue iba ak je shortcode na stránke alebo na shop/archive stránke.
		if ( ! $this->should_enqueue() ) {
			return;
		}

		// Frontend CSS.
		wp_enqueue_style(
			'wc-sf-frontend',
			WC_SF_PLUGIN_URL . 'assets/css/frontend.css',
			[],
			WC_SF_VERSION
		);

		// jQuery UI Slider (vstavaný vo WP — nepotrebuje externe CDN).
		wp_enqueue_script( 'jquery-ui-slider' );

		// Frontend JS.
		wp_enqueue_script(
			'wc-sf-frontend',
			WC_SF_PLUGIN_URL . 'assets/js/frontend.js',
			[ 'jquery', 'jquery-ui-slider' ],
			WC_SF_VERSION,
			true
		);

		// Lokalizácia JS dát.
		wp_localize_script(
			'wc-sf-frontend',
			'WC_SF_Frontend',
			$this->get_js_data()
		);
	}

	/**
	 * Vráti pole dát pre lokalizáciu JS.
	 *
	 * @return array<string, mixed>
	 */
	private function get_js_data(): array {
		$settings = get_option( 'wc_sf_settings', [] );

		return [
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'wc_sf_frontend_nonce' ),
			'filterMode'     => $settings['filter_mode'] ?? 'ajax',
			'i18n'           => [
				'viewMore'     => __( 'Zobraziť viac', 'wc-simple-filter' ),
				'viewLess'     => __( 'Zobraziť menej', 'wc-simple-filter' ),
				'resetAll'     => $settings['reset_button_text'] ?? __( 'Zrušiť filtre', 'wc-simple-filter' ),
				'closeLabel'   => __( 'Zavrieť', 'wc-simple-filter' ),
				'removeFilter' => __( 'Odstrániť filter', 'wc-simple-filter' ),
			],
		];
	}

	/**
	 * Skontroluje, či sa majú načítať assets na aktuálnej stránke.
	 * Vždy načíta — shortcode sa môže objaviť kdekoľvek.
	 * Výkon sa rieši cez podmienené načítavanie v budúcnosti.
	 *
	 * @return bool
	 */
	private function should_enqueue(): bool {
		/**
		 * Filter na kontrolu, či načítať frontend assets.
		 *
		 * @param bool $should Načítať assets? Default true.
		 */
		return (bool) apply_filters( 'wc_sf_enqueue_frontend_assets', true );
	}
}
