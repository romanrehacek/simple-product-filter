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
		add_action( 'wp_ajax_wc_sf_save_filter',     [ $this, 'save_filter' ] );
		add_action( 'wp_ajax_wc_sf_delete_filter',   [ $this, 'delete_filter' ] );
		add_action( 'wp_ajax_wc_sf_reorder_filters', [ $this, 'reorder_filters' ] );
		add_action( 'wp_ajax_wc_sf_reindex',         [ $this, 'reindex' ] );
		add_action( 'wp_ajax_wc_sf_get_type_values', [ $this, 'get_type_values' ] );
		add_action( 'wp_ajax_wc_sf_save_settings',   [ $this, 'save_settings' ] );
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
			return [
				[ 'value' => 'instock',     'label' => __( 'Na sklade', 'wc-simple-filter' ) ],
				[ 'value' => 'outofstock',  'label' => __( 'Vypredané', 'wc-simple-filter' ) ],
				[ 'value' => 'onbackorder', 'label' => __( 'Na objednávku', 'wc-simple-filter' ) ],
			];
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
	 * Extrahuje a sanitizuje dáta filtra z $_POST.
	 *
	 * @return array<string, mixed>
	 */
	private function extract_filter_data(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$raw_config = isset( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : '{}';

		// Ak prišlo ako string (JSON), dekódujeme. Ak ako array, berieme priamo.
		if ( is_string( $raw_config ) ) {
			$config = json_decode( $raw_config, true ) ?? [];
		} else {
			$config = (array) $raw_config;
		}

		return [
			'filter_type'  => sanitize_text_field( wp_unslash( $_POST['filter_type'] ?? '' ) ),
			'filter_style' => sanitize_text_field( wp_unslash( $_POST['filter_style'] ?? 'checkbox' ) ),
			'label'        => sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ),
			'show_label'   => isset( $_POST['show_label'] ) ? 1 : 0,
			'config'       => $config,
		];
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
