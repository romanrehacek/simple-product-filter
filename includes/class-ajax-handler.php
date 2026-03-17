<?php
/**
 * AJAX handler — spracúva všetky admin AJAX požiadavky pluginu.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trieda Ajax_Handler.
 */
class Ajax_Handler {

	/**
	 * Zaregistruje AJAX hooky.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Admin AJAX endpointy.
		add_action( 'wp_ajax_wc_sf_save_filter',     [ $this, 'save_filter' ] );
		add_action( 'wp_ajax_wc_sf_delete_filter',   [ $this, 'delete_filter' ] );
		add_action( 'wp_ajax_wc_sf_reorder_filters', [ $this, 'reorder_filters' ] );
		add_action( 'wp_ajax_wc_sf_reindex',         [ $this, 'reindex' ] );
		add_action( 'wp_ajax_wc_sf_get_type_values', [ $this, 'get_type_values' ] );
		add_action( 'wp_ajax_wc_sf_save_settings',   [ $this, 'save_settings' ] );

		// Frontend AJAX endpoint — dostupný pre prihlásených aj neprihlásených.
		add_action( 'wp_ajax_wc_sf_filter_products',        [ $this, 'filter_products' ] );
		add_action( 'wp_ajax_nopriv_wc_sf_filter_products', [ $this, 'filter_products' ] );
	}

	/**
	 * Overí nonce a kapacitu. Pri neúspechu ukončí s chybou.
	 *
	 * @return void
	 */
	private function verify(): void {
		check_ajax_referer( 'wc_sf_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnenie.', 'wc-simple-filter' ) ], 403 );
		}
	}

	/**
	 * Uloží nový alebo aktualizuje existujúci filter.
	 *
	 * @return void
	 */
	public function save_filter(): void {
		$this->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$data   = $this->extract_filter_data();

		if ( empty( $data['filter_type'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Typ filtra je povinný.', 'wc-simple-filter' ) ] );
		}

		if ( $id > 0 ) {
			$success = Filter_Manager::update( $id, $data );
			$filter  = Filter_Manager::get( $id );
		} else {
			$new_id  = Filter_Manager::create( $data );
			$success = false !== $new_id;
			$filter  = $success ? Filter_Manager::get( $new_id ) : null;
		}

		if ( ! $success || ! $filter ) {
			wp_send_json_error( [ 'message' => __( 'Chyba pri ukladaní filtra.', 'wc-simple-filter' ) ] );
		}

		wp_send_json_success( [ 'filter' => $filter ] );
	}

	/**
	 * Zmaže filter.
	 *
	 * @return void
	 */
	public function delete_filter(): void {
		$this->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = absint( $_POST['id'] ?? 0 );

		if ( $id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Neplatné ID filtra.', 'wc-simple-filter' ) ] );
		}

		$success = Filter_Manager::delete( $id );

		if ( ! $success ) {
			wp_send_json_error( [ 'message' => __( 'Chyba pri mazaní filtra.', 'wc-simple-filter' ) ] );
		}

		wp_send_json_success();
	}

	/**
	 * Uloží nové poradie filtrov po drag & drop.
	 *
	 * @return void
	 */
	public function reorder_filters(): void {
		$this->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_order = isset( $_POST['order'] ) ? (array) $_POST['order'] : [];
		$order     = array_map( 'absint', $raw_order );
		$order     = array_filter( $order );

		if ( empty( $order ) ) {
			wp_send_json_error( [ 'message' => __( 'Neplatné poradie.', 'wc-simple-filter' ) ] );
		}

		Filter_Manager::reorder( $order );
		wp_send_json_success();
	}

	/**
	 * Spustí full rebuild indexu.
	 *
	 * @return void
	 */
	public function reindex(): void {
		$this->verify();

		$filters = Filter_Manager::get_all();

		if ( empty( $filters ) ) {
			wp_send_json_success( [ 'count' => 0, 'message' => __( 'Žiadne filtre na indexovanie.', 'wc-simple-filter' ) ] );
		}

		$filter_types = array_unique( array_column( $filters, 'filter_type' ) );
		$total        = Index_Manager::rebuild( $filter_types );

		wp_send_json_success( [
			'count'   => $total,
			'message' => sprintf(
				/* translators: %d: počet záznamov */
				__( 'Index prebudovaný. Spracovaných: %d záznamov.', 'wc-simple-filter' ),
				$total
			),
		] );
	}

	/**
	 * Vráti dostupné hodnoty pre daný filter_type (pre values picker v edit stránke).
	 *
	 * @return void
	 */
	public function get_type_values(): void {
		$this->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$filter_type = sanitize_text_field( wp_unslash( $_POST['filter_type'] ?? '' ) );

		if ( empty( $filter_type ) ) {
			wp_send_json_error( [ 'message' => __( 'Chýba filter_type.', 'wc-simple-filter' ) ] );
		}

		$values = $this->load_available_values( $filter_type );
		wp_send_json_success( [ 'values' => $values ] );
	}

	/**
	 * Uloží všeobecné nastavenia pluginu.
	 *
	 * @return void
	 */
	public function save_settings(): void {
		$this->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$posted = isset( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : [];

		$current  = get_option( 'wc_sf_settings', Filter_Manager::default_settings() );
		$allowed  = array_keys( Filter_Manager::default_settings() );
		$settings = $current;

		foreach ( $allowed as $key ) {
			if ( ! isset( $posted[ $key ] ) ) {
				// Checkboxy posielajú hodnotu len keď sú zaškrtnuté.
				if ( in_array( $key, [ 'show_reset_button', 'hide_empty', 'delete_on_uninstall' ], true ) ) {
					$settings[ $key ] = false;
				}

				continue;
			}

			$settings[ $key ] = match ( $key ) {
				'filter_mode'         => in_array( $posted[ $key ], [ 'ajax', 'submit', 'reload' ], true ) ? $posted[ $key ] : 'ajax',
				'show_reset_button',
				'hide_empty',
				'delete_on_uninstall' => (bool) $posted[ $key ],
				default               => sanitize_text_field( $posted[ $key ] ),
			};
		}

		update_option( 'wc_sf_settings', $settings );
		wp_send_json_success( [ 'message' => __( 'Nastavenia uložené.', 'wc-simple-filter' ) ] );
	}

	/**
	 * Načíta dostupné hodnoty pre daný filter_type z WooCommerce.
	 *
	 * @param string $filter_type Typ filtra.
	 * @return array<int, array<string, mixed>>
	 */
	private function load_available_values( string $filter_type ): array {
		if ( 'brand' === $filter_type ) {
			return $this->get_term_values( 'product_brand' );
		}

		if ( str_starts_with( $filter_type, 'attribute_' ) ) {
			$taxonomy = substr( $filter_type, strlen( 'attribute_' ) );
			return $this->get_term_values( $taxonomy );
		}

		if ( 'status' === $filter_type ) {
			// Načítame všetky registrované stavy vrátane custom (cez woocommerce_product_stock_status_options).
			$stock_statuses = wc_get_product_stock_status_options();
			$values         = [];

			foreach ( $stock_statuses as $slug => $label ) {
				$values[] = [ 'value' => $slug, 'label' => $label ];
			}

			return $values;
		}

		if ( 'sale' === $filter_type ) {
			return [
				[ 'value' => 'on_sale',  'label' => __( 'V akcii', 'wc-simple-filter' ) ],
				[ 'value' => 'off_sale', 'label' => __( 'Bez zľavy', 'wc-simple-filter' ) ],
			];
		}

		if ( str_starts_with( $filter_type, 'meta_' ) ) {
			$meta_key = substr( $filter_type, strlen( 'meta_' ) );
			return $this->get_meta_values( $meta_key );
		}

		return [];
	}

	/**
	 * Načíta termy z taxonomie ako pole value/label.
	 *
	 * @param string $taxonomy Taxonomia.
	 * @return array<int, array<string, string>>
	 */
	private function get_term_values( string $taxonomy ): array {
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
	 * Načíta unikátne hodnoty meta kľúča.
	 *
	 * @param string $meta_key Meta kľúč.
	 * @return array<int, array<string, string>>
	 */
	private function get_meta_values( string $meta_key ): array {
		global $wpdb;

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

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map( fn( $row ) => [
			'value' => $row['value'],
			'label' => $row['value'],
		], $rows );
	}

	/**
	 * AJAX endpoint pre filtrovanie produktov (frontend).
	 *
	 * Dostupný pre prihlásených aj neprihlásených používateľov.
	 * Overenie: frontend nonce (wc_sf_frontend_nonce).
	 *
	 * Vstup (POST):
	 *  - nonce    string  Nonce overenia.
	 *  - wcsf     array   Sanitizované filter parametre (rovnaká štruktúra ako $_GET['wcsf']).
	 *  - paged    int     Číslo stránky (default 1).
	 *  - orderby  string  WC orderby hodnota (nepovinné).
	 *
	 * Výstup (JSON success):
	 *  - html         string  HTML product loop.
	 *  - pagination   string  HTML stránkovania.
	 *  - found_posts  int     Celkový počet nájdených produktov.
	 *  - max_pages    int     Celkový počet stránok.
	 *  - paged        int     Aktuálna stránka.
	 *
	 * @return void
	 */
	public function filter_products(): void {
		// Overenie frontend nonce — CSRF ochrana.
		// Kapacitná kontrola nie je potrebná (endpoint je verejný, len čítame).
		check_ajax_referer( 'wc_sf_frontend_nonce', 'nonce' );

		// --- Sanitizácia vstupu ---

		// Filter parametre — rovnaký mechanizmus ako pre $_GET, ale z $_POST.
		// Dočasne presunieme wcsf z POST do GET aby Query_Builder::get_active_params() fungoval,
		// ale radšej použijeme priamy sanitizačný kód aby sme nemodifikovali globály.
		$raw_wcsf = isset( $_POST['wcsf'] ) && is_array( $_POST['wcsf'] )
			? (array) wp_unslash( $_POST['wcsf'] )
			: [];

		$params = $this->sanitize_frontend_params( $raw_wcsf );

		// Stránkovanie.
		$paged    = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;

		// Zoradenie — validujeme voči povoleným WC hodnotám.
		$raw_orderby = isset( $_POST['orderby'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['orderby'] ) )
			: '';
		$orderby = in_array( $raw_orderby, Query_Builder::ALLOWED_ORDERBY, true )
			? $raw_orderby
			: get_option( 'woocommerce_default_catalog_orderby', 'menu_order' );

		// --- Zostavenie WP_Query args ---

		$filter_configs = Filter_Manager::get_all();
		$filter_args    = Query_Builder::build( $params, $filter_configs );

		/**
		 * Počet produktov na stránku pre AJAX endpoint.
		 * Default: WooCommerce nastavenie.
		 *
		 * @param int $per_page Počet produktov.
		 */
		$per_page = (int) apply_filters(
			'wc_sf_ajax_per_page',
			(int) get_option( 'posts_per_page', 12 )
		);

		$query_args = array_merge(
			[
				'post_type'           => 'product',
				'post_status'         => 'publish',
				'posts_per_page'      => $per_page,
				'paged'               => $paged,
				'ignore_sticky_posts' => true,
			],
			$filter_args
		);

		// Aplikuj WC zoradenie.
		$query_args = $this->apply_orderby( $query_args, $orderby );

		/**
		 * Filter na úpravu WP_Query args pre AJAX filtrovanie.
		 *
		 * @param array<string, mixed> $query_args    Zostavené args.
		 * @param array<string, mixed> $params        Aktívne filter parametre.
		 * @param int                  $paged         Číslo stránky.
		 * @param string               $orderby       Zoradenie.
		 */
		$query_args = (array) apply_filters( 'wc_sf_ajax_query_args', $query_args, $params, $paged, $orderby );

		// --- Spustenie query a render ---

		$query = new \WP_Query( $query_args );

		// Render product loop HTML.
		ob_start();

		if ( $query->have_posts() ) {
			wc_set_loop_prop( 'current_page', $paged );
			wc_set_loop_prop( 'total_pages', $query->max_num_pages );
			wc_set_loop_prop( 'is_shortcode', false );

			woocommerce_product_loop_start();

			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}

			woocommerce_product_loop_end();
		} else {
			// Prázdny stav — zabalíme do rovnakého wrappera ako loop-start.php,
			// aby JS mohol nájsť [data-wcsf-products] aj pri prázdnom výsledku.
			echo '<div class="child-category__products" data-wcsf-products>';
			wc_get_template( 'loop/no-products-found.php' );
			echo '</div>';
		}

		$products_html = (string) ob_get_clean();
		wp_reset_postdata();

		// Render pagination HTML.
		ob_start();

		if ( $query->max_num_pages > 1 ) {
			woocommerce_pagination();
		}

		$pagination_html = (string) ob_get_clean();

		/**
		 * Akcia po vykreslení produktov (napr. re-init WC JS hooky).
		 *
		 * @param \WP_Query            $query   WP_Query objekt.
		 * @param array<string, mixed> $params  Aktívne filter parametre.
		 */
		do_action( 'wc_sf_after_products_render', $query, $params );

		wp_send_json_success( [
			'html'        => $products_html,
			'pagination'  => $pagination_html,
			'found_posts' => (int) $query->found_posts,
			'max_pages'   => (int) $query->max_num_pages,
			'paged'       => $paged,
		] );
	}

	/**
	 * Sanitizuje pole frontend filter parametrov (z AJAX POST).
	 *
	 * Replikuje logiku Query_Builder::get_active_params() ale číta z poľa
	 * namiesto priamo z $_GET, aby sme nemodifikovali globálne superglobals.
	 *
	 * @param  array<mixed> $raw  Nesanitizovaný vstup (z wp_unslash()).
	 * @return array<string, mixed>
	 */
	private function sanitize_frontend_params( array $raw ): array {
		$params = [];

		foreach ( $raw as $raw_key => $raw_value ) {
			$key = sanitize_key( (string) $raw_key );

			if ( empty( $key ) ) {
				continue;
			}

			// Validácia kľúča.
			if ( ! $this->is_valid_filter_key( $key ) ) {
				continue;
			}

			// Slider — price min/max.
			if ( 'price' === $key && is_array( $raw_value ) ) {
				$min = isset( $raw_value['min'] ) && '' !== $raw_value['min']
					? (float) $raw_value['min']
					: null;
				$max = isset( $raw_value['max'] ) && '' !== $raw_value['max']
					? (float) $raw_value['max']
					: null;

				if ( null !== $min || null !== $max ) {
					$params['price'] = array_filter(
						[ 'min' => $min, 'max' => $max ],
						static fn( $v ) => null !== $v
					);
				}
				continue;
			}

			// Attribute/meta slider.
			if (
				is_array( $raw_value )
				&& array_key_exists( 'min', $raw_value )
				&& array_key_exists( 'max', $raw_value )
				&& ( str_starts_with( $key, 'attribute_' ) || str_starts_with( $key, 'meta_' ) )
			) {
				$min = '' !== $raw_value['min'] ? (float) $raw_value['min'] : null;
				$max = '' !== $raw_value['max'] ? (float) $raw_value['max'] : null;

				if ( null !== $min || null !== $max ) {
					$params[ $key ] = array_filter(
						[ 'min' => $min, 'max' => $max ],
						static fn( $v ) => null !== $v
					);
				}
				continue;
			}

			// Skalárna hodnota.
			if ( ! is_array( $raw_value ) ) {
				$value = sanitize_text_field( (string) $raw_value );
				if ( '' !== $value ) {
					$params[ $key ] = $value;
				}
				continue;
			}

			// Pole hodnôt.
			$sanitized = [];
			foreach ( $raw_value as $v ) {
				$s = sanitize_text_field( (string) $v );
				if ( '' !== $s ) {
					$sanitized[] = $s;
				}
			}

			if ( ! empty( $sanitized ) ) {
				$params[ $key ] = $sanitized;
			}
		}

		return $params;
	}

	/**
	 * Validuje filter kľúč — rovnaká logika ako v Query_Builder.
	 *
	 * @param  string $key  Sanitizovaný kľúč.
	 * @return bool
	 */
	private function is_valid_filter_key( string $key ): bool {
		$static = [ 'brand', 'status', 'sale', 'price' ];

		if ( in_array( $key, $static, true ) ) {
			return true;
		}

		if ( str_starts_with( $key, 'attribute_' ) ) {
			return strlen( $key ) > strlen( 'attribute_' );
		}

		if ( str_starts_with( $key, 'meta_' ) ) {
			return strlen( $key ) > strlen( 'meta_' );
		}

		return false;
	}

	/**
	 * Aplikuje WooCommerce orderby na WP_Query args.
	 *
	 * @param  array<string, mixed> $args     WP_Query args.
	 * @param  string               $orderby  WC orderby hodnota.
	 * @return array<string, mixed>
	 */
	private function apply_orderby( array $args, string $orderby ): array {
		switch ( $orderby ) {
			case 'date':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;

			case 'price':
				$args['orderby']  = 'meta_value_num'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_orderby
				$args['order']    = 'ASC';
				$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;

			case 'price-desc':
				$args['orderby']  = 'meta_value_num'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_orderby
				$args['order']    = 'DESC';
				$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;

			case 'popularity':
				$args['orderby']  = 'meta_value_num'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_orderby
				$args['order']    = 'DESC';
				$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;

			case 'rating':
				$args['orderby']  = 'meta_value_num'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_orderby
				$args['order']    = 'DESC';
				$args['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;

			case 'title':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;

			case 'menu_order':
			default:
				$args['orderby'] = 'menu_order title';
				$args['order']   = 'ASC';
				break;
		}

		return $args;
	}
	/**
	 * Extrahuje a sanitizuje dáta filtra z POST requestu.
	 *
	 * @return array<string, mixed>
	 */
	private function extract_filter_data(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$raw_config  = isset( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : '{}';
		$filter_type = sanitize_text_field( wp_unslash( $_POST['filter_type'] ?? '' ) );
		$label       = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );

		// Ak label nie je zadaný, vygenerujeme pekný defaultný názov.
		if ( '' === $label ) {
			$label = self::default_label_for_type( $filter_type );
		}

		// Ak prišlo ako string (JSON), dekódujeme. Ak ako array, berieme priamo.
		if ( is_string( $raw_config ) ) {
			$config = json_decode( $raw_config, true ) ?? [];
		} else {
			$config = (array) $raw_config;
		}

		return [
			'filter_type'  => $filter_type,
			'filter_style' => sanitize_text_field( wp_unslash( $_POST['filter_style'] ?? 'checkbox' ) ),
			'label'        => $label,
			'show_label'   => isset( $_POST['show_label'] ) ? 1 : 0,
			'config'       => $config,
		];
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Vráti pekný defaultný názov filtra pre daný filter_type.
	 *
	 * Používa sa keď admin neposkytol vlastný názov.
	 *
	 * @param string $filter_type Typ filtra (napr. 'brand', 'price', 'attribute_pa_farba').
	 * @return string
	 */
	public static function default_label_for_type( string $filter_type ): string {
		// Základné typy.
		$labels = [
			'brand'  => __( 'Značka', 'wc-simple-filter' ),
			'price'  => __( 'Cena', 'wc-simple-filter' ),
			'status' => __( 'Dostupnosť', 'wc-simple-filter' ),
			'sale'   => __( 'Akcia', 'wc-simple-filter' ),
		];

		if ( isset( $labels[ $filter_type ] ) ) {
			return $labels[ $filter_type ];
		}

		// WC atribúty: attribute_pa_farba → načítame label z WC.
		if ( str_starts_with( $filter_type, 'attribute_pa_' ) ) {
			$attr_name = substr( $filter_type, strlen( 'attribute_pa_' ) );
			$taxonomy  = wc_attribute_taxonomy_name( $attr_name );
			$attribute = wc_get_attribute( wc_attribute_taxonomy_id_by_name( $taxonomy ) );

			if ( $attribute && ! empty( $attribute->name ) ) {
				return $attribute->name;
			}

			// Fallback: capitalize slug.
			return ucfirst( str_replace( [ '_', '-' ], ' ', $attr_name ) );
		}

		// Custom meta: meta_my_key → "My key".
		if ( str_starts_with( $filter_type, 'meta_' ) ) {
			$key = substr( $filter_type, strlen( 'meta_' ) );
			return ucfirst( str_replace( [ '_', '-' ], ' ', $key ) );
		}

		// Posledný fallback.
		return ucfirst( str_replace( [ '_', '-' ], ' ', $filter_type ) );
	}
}
