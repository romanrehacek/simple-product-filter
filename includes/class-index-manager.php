<?php
/**
 * Správa indexu hodnôt filtrov — cache pre hide-empty logiku.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trieda Index_Manager.
 *
 * Zodpovedá za:
 * - budovanie a aktualizáciu tabuľky wc_sf_index
 * - transient cache nad indexom
 * - inkrementálny update pri zmene produktu
 */
class Index_Manager {

	/**
	 * Názov tabuľky indexu (bez prefixu).
	 */
	const TABLE_INDEX = 'wc_sf_index';

	/**
	 * TTL transient cache v sekundách (12 hodín).
	 */
	const CACHE_TTL = 43200;

	/**
	 * Prefix pre transient kľúče.
	 */
	const CACHE_PREFIX = 'wc_sf_index_';

	/**
	 * Zaregistruje WordPress hooky pre inkrementálny update.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'save_post_product', [ $this, 'on_product_save' ], 20, 1 );
		add_action( 'woocommerce_update_product', [ $this, 'on_product_save' ], 20, 1 );
		add_action( 'woocommerce_product_set_stock_status', [ $this, 'on_product_save' ], 20, 1 );
		add_action( 'transition_post_status', [ $this, 'on_post_status_change' ], 20, 3 );
	}

	/**
	 * Vytvorí DB tabuľku indexu. Volá sa z Filter_Manager::install().
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_index     = $wpdb->prefix . self::TABLE_INDEX;

		$sql = "CREATE TABLE {$table_index} (
			id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
			filter_type   VARCHAR(100)  NOT NULL DEFAULT '',
			value         VARCHAR(255)  NOT NULL DEFAULT '',
			product_count INT UNSIGNED  NOT NULL DEFAULT 0,
			updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY filter_type_value (filter_type(100), value(191))
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Zmaže DB tabuľku indexu. Volá sa z uninstall.php.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::TABLE_INDEX ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		self::flush_all_cache();
	}

	/**
	 * Vráti počet produktov pre danú hodnotu filtra.
	 * Výsledok je cachovaný v transiente.
	 *
	 * @param string $filter_type Typ filtra.
	 * @return array<string, int> Mapa value => product_count.
	 */
	public static function get_counts( string $filter_type ): array {
		$cache_key = self::CACHE_PREFIX . md5( $filter_type );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_INDEX;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT value, product_count FROM {$table} WHERE filter_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$filter_type
			),
			ARRAY_A
		);

		$counts = [];

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$counts[ $row['value'] ] = (int) $row['product_count'];
			}
		}

		set_transient( $cache_key, $counts, self::CACHE_TTL );

		return $counts;
	}

	/**
	 * Spustí full rebuild indexu pre všetky typy filtrov.
	 *
	 * @param string[] $filter_types Zoznam typov na prebudovanie.
	 * @return int Počet spracovaných záznamov.
	 */
	public static function rebuild( array $filter_types ): int {
		$total = 0;

		foreach ( $filter_types as $filter_type ) {
			$total += self::rebuild_type( $filter_type );
		}

		return $total;
	}

	/**
	 * Prebuduje index pre jeden filter_type.
	 *
	 * @param string $filter_type Typ filtra.
	 * @return int Počet uložených záznamov.
	 */
	public static function rebuild_type( string $filter_type ): int {
		$counts = self::compute_counts( $filter_type );

		self::save_counts( $filter_type, $counts );

		// Invaliduj transient cache.
		$cache_key = self::CACHE_PREFIX . md5( $filter_type );
		delete_transient( $cache_key );

		return count( $counts );
	}

	/**
	 * Vypočíta počty produktov pre daný filter_type.
	 *
	 * @param string $filter_type Typ filtra.
	 * @return array<string, int> Mapa value => count.
	 */
	private static function compute_counts( string $filter_type ): array {
		if ( str_starts_with( $filter_type, 'attribute_' ) ) {
			$taxonomy = substr( $filter_type, strlen( 'attribute_' ) );
			return self::compute_taxonomy_counts( $taxonomy );
		}

		if ( 'brand' === $filter_type ) {
			return self::compute_taxonomy_counts( 'product_brand' );
		}

		if ( str_starts_with( $filter_type, 'meta_' ) ) {
			$meta_key = substr( $filter_type, strlen( 'meta_' ) );
			return self::compute_meta_counts( $meta_key );
		}

		if ( 'status' === $filter_type ) {
			return self::compute_stock_status_counts();
		}

		if ( 'sale' === $filter_type ) {
			return self::compute_sale_counts();
		}

		return [];
	}

	/**
	 * Vypočíta počty pre taxonomy (atribúty, brand).
	 *
	 * @param string $taxonomy Názov taxonomie.
	 * @return array<string, int>
	 */
	private static function compute_taxonomy_counts( string $taxonomy ): array {
		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'all',
		] );

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		$counts = [];

		foreach ( $terms as $term ) {
			// Počítame len publikované produkty.
			$count = self::count_published_products_with_term( $term->term_id, $taxonomy );
			$counts[ $term->slug ] = $count;
		}

		return $counts;
	}

	/**
	 * Vypočíta počty pre meta kľúč.
	 *
	 * @param string $meta_key Meta kľúč.
	 * @return array<string, int>
	 */
	private static function compute_meta_counts( string $meta_key ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value, COUNT(DISTINCT p.ID) as cnt
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE p.post_type = 'product'
				AND p.post_status = 'publish'
				AND pm.meta_key = %s
				AND pm.meta_value != ''
				GROUP BY pm.meta_value",
				$meta_key
			),
			ARRAY_A
		);

		$counts = [];

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$counts[ $row['meta_value'] ] = (int) $row['cnt'];
			}
		}

		return $counts;
	}

	/**
	 * Vypočíta počty pre stav skladu.
	 *
	 * @return array<string, int>
	 */
	private static function compute_stock_status_counts(): array {
		global $wpdb;

		$statuses = [ 'instock', 'outofstock', 'onbackorder' ];
		$counts   = [];

		foreach ( $statuses as $status ) {
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT p.ID)
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
					WHERE p.post_type = 'product'
					AND p.post_status = 'publish'
					AND pm.meta_key = '_stock_status'
					AND pm.meta_value = %s",
					$status
				)
			);

			$counts[ $status ] = $count;
		}

		return $counts;
	}

	/**
	 * Vypočíta počty pre zľavové filtre.
	 *
	 * @return array<string, int>
	 */
	private static function compute_sale_counts(): array {
		$on_sale_ids = wc_get_product_ids_on_sale();

		$in_sale_count = count( $on_sale_ids );

		$total = (int) wp_count_posts( 'product' )->publish;

		return [
			'on_sale'  => $in_sale_count,
			'off_sale' => max( 0, $total - $in_sale_count ),
		];
	}

	/**
	 * Počet publikovaných produktov s daným termom.
	 *
	 * @param int    $term_id  ID termu.
	 * @param string $taxonomy Taxonomia.
	 * @return int
	 */
	private static function count_published_products_with_term( int $term_id, string $taxonomy ): int {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				WHERE tt.term_id = %d
				AND tt.taxonomy = %s
				AND p.post_type = 'product'
				AND p.post_status = 'publish'",
				$term_id,
				$taxonomy
			)
		);

		return (int) $count;
	}

	/**
	 * Uloží počty do index tabuľky (INSERT ... ON DUPLICATE KEY UPDATE).
	 *
	 * @param string           $filter_type Typ filtra.
	 * @param array<string,int> $counts     Mapa value => count.
	 * @return void
	 */
	private static function save_counts( string $filter_type, array $counts ): void {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_INDEX;

		// Najprv zmažeme staré záznamy pre tento filter_type.
		$wpdb->delete( $table, [ 'filter_type' => $filter_type ], [ '%s' ] );

		foreach ( $counts as $value => $count ) {
			$wpdb->replace(
				$table,
				[
					'filter_type'   => $filter_type,
					'value'         => (string) $value,
					'product_count' => (int) $count,
				],
				[ '%s', '%s', '%d' ]
			);
		}
	}

	/**
	 * Hook: inkrementálny update pri uložení produktu.
	 *
	 * @param int $product_id ID produktu.
	 * @return void
	 */
	public function on_product_save( int $product_id ): void {
		// Ignoruj auto-drafts a revízie.
		if ( wp_is_post_revision( $product_id ) || wp_is_post_autosave( $product_id ) ) {
			return;
		}

		$this->invalidate_product_related_cache( $product_id );
	}

	/**
	 * Hook: zmena stavu publikovania produktu.
	 *
	 * @param string   $new_status Nový stav.
	 * @param string   $old_status Starý stav.
	 * @param \WP_Post $post       Post objekt.
	 * @return void
	 */
	public function on_post_status_change( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'product' !== $post->post_type ) {
			return;
		}

		if ( $new_status === $old_status ) {
			return;
		}

		$this->invalidate_product_related_cache( $post->ID );
	}

	/**
	 * Invaliduje cache pre typy filtrov dotknuté konkrétnym produktom.
	 * Plný rebuild sa spustí pri nasledujúcom volaní get_counts().
	 *
	 * @param int $product_id ID produktu.
	 * @return void
	 */
	private function invalidate_product_related_cache( int $product_id ): void {
		// Invaliduj brand.
		delete_transient( self::CACHE_PREFIX . md5( 'brand' ) );

		// Invaliduj status + sale.
		delete_transient( self::CACHE_PREFIX . md5( 'status' ) );
		delete_transient( self::CACHE_PREFIX . md5( 'sale' ) );

		// Invaliduj atribúty tohto produktu.
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return;
		}

		$attributes = $product->get_attributes();

		foreach ( array_keys( $attributes ) as $taxonomy ) {
			$filter_type = 'attribute_' . $taxonomy;
			delete_transient( self::CACHE_PREFIX . md5( $filter_type ) );
		}
	}

	/**
	 * Zmaže všetky transient cache indexu.
	 *
	 * @return void
	 */
	public static function flush_all_cache(): void {
		global $wpdb;

		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_sf_index_%' OR option_name LIKE '_transient_timeout_wc_sf_index_%'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}
}
