<?php
/**
 * Filter management — CRUD operations and database installation.
 *
 * @package Simple_Product_Filter
 */

namespace Simple_Product_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Filter_Manager.
 *
 * Responsible for:
 * - creating database tables on plugin activation
 * - CRUD operations on the spf_filters table
 */
class Filter_Manager {

	/**
	 * Table name for filters (without prefix).
	 */
	const TABLE_FILTERS = 'spf_filters';

	/**
	 * Database schema version.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Creates database tables. Called during plugin activation.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_filters   = $wpdb->prefix . self::TABLE_FILTERS;

		$sql = "CREATE TABLE {$table_filters} (
			id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
			sort_order   INT UNSIGNED    NOT NULL DEFAULT 0,
			filter_type  VARCHAR(100)    NOT NULL DEFAULT '',
			filter_style VARCHAR(20)     NOT NULL DEFAULT 'checkbox',
			label        VARCHAR(255)    NOT NULL DEFAULT '',
			show_label   TINYINT(1)      NOT NULL DEFAULT 1,
			config       LONGTEXT        NOT NULL,
			created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY sort_order (sort_order),
			KEY filter_type (filter_type)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Create index table.
		require_once SPF_PLUGIN_DIR . 'includes/class-index-manager.php';
		Index_Manager::install();

		// Store schema version.
		update_option( 'spf_db_version', self::DB_VERSION );

		// Store default settings if they don't exist yet.
		if ( false === get_option( 'spf_settings' ) ) {
			add_option( 'spf_settings', self::default_settings() );
		}
	}

	/**
	 * Returns default plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return [
			'filter_mode'          => 'ajax',
			'filter_button_text'   => __( 'Filter', 'simple-product-filter' ),
			'show_reset_button'    => true,
			'reset_button_text'    => __( 'Reset filters', 'simple-product-filter' ),
			'hide_empty'           => true,
			'delete_on_uninstall'  => false,
		];
	}

	/**
	 * Returns all filters ordered by sort_order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all(): array {
		global $wpdb;

		// Try cache first.
		$cache_key = 'spf_filters_all';
		$cached    = wp_cache_get( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$table = $wpdb->prefix . self::TABLE_FILTERS;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC", ARRAY_A );

		if ( ! is_array( $rows ) ) {
			wp_cache_set( $cache_key, [], '', 3600 );
			return [];
		}

		$result = array_map( [ self::class, 'parse_row' ], $rows );
		wp_cache_set( $cache_key, $result, '', 3600 );
		return $result;
	}

	/**
	 * Returns a single filter by ID.
	 *
	 * @param int $id Filter ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;

		// Try cache first.
		$cache_key = 'spf_filter_' . $id;
		$cached    = wp_cache_get( $cache_key );

		if ( false !== $cached ) {
			return 'null' === $cached ? null : $cached;
		}

		$table = $wpdb->prefix . self::TABLE_FILTERS;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is constructed and safe
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			wp_cache_set( $cache_key, 'null', '', 3600 );
			return null;
		}

		$result = self::parse_row( $row );
		wp_cache_set( $cache_key, $result, '', 3600 );
		return $result;
	}

	/**
	 * Creates a new filter.
	 *
	 * @param array<string, mixed> $data Filter data.
	 * @return int|false New filter ID or false on error.
	 */
	public static function create( array $data ): int|false {
		global $wpdb;

		$table      = $wpdb->prefix . self::TABLE_FILTERS;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$max_order  = (int) $wpdb->get_var( "SELECT MAX(sort_order) FROM {$table}" );

		$insert = [
			'sort_order'   => $max_order + 1,
			'filter_type'  => sanitize_text_field( $data['filter_type'] ?? '' ),
			'filter_style' => sanitize_text_field( $data['filter_style'] ?? 'checkbox' ),
			'label'        => sanitize_text_field( $data['label'] ?? '' ),
			'show_label'   => isset( $data['show_label'] ) ? (int) $data['show_label'] : 1,
			'config'       => wp_json_encode( $data['config'] ?? new \stdClass() ),
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $insert );

		if ( false === $result ) {
			return false;
		}

		// Flush cache after insert.
		wp_cache_delete( 'spf_filters_all' );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates an existing filter.
	 *
	 * @param int                  $id   Filter ID.
	 * @param array<string, mixed> $data New data.
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_FILTERS;

		$update = [];

		if ( isset( $data['filter_style'] ) ) {
			$update['filter_style'] = sanitize_text_field( $data['filter_style'] );
		}

		if ( isset( $data['label'] ) ) {
			$update['label'] = sanitize_text_field( $data['label'] );
		}

		if ( isset( $data['show_label'] ) ) {
			$update['show_label'] = (int) $data['show_label'];
		}

		if ( isset( $data['config'] ) ) {
			$update['config'] = wp_json_encode( $data['config'] );
		}

		if ( empty( $update ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			$update,
			[ 'id' => $id ]
		);

		if ( false !== $result ) {
			// Flush cache after update.
			wp_cache_delete( 'spf_filters_all' );
			wp_cache_delete( 'spf_filter_' . $id );
		}

		return false !== $result;
	}

	/**
	 * Deletes a filter by ID.
	 *
	 * @param int $id Filter ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		$table  = $wpdb->prefix . self::TABLE_FILTERS;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

		if ( false !== $result ) {
			// Flush cache after delete.
			wp_cache_delete( 'spf_filters_all' );
			wp_cache_delete( 'spf_filter_' . $id );
		}

		return false !== $result;
	}

	/**
	 * Updates the filter order.
	 *
	 * @param int[] $ordered_ids Array of IDs in new order.
	 * @return bool
	 */
	public static function reorder( array $ordered_ids ): bool {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_FILTERS;

		foreach ( $ordered_ids as $position => $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				[ 'sort_order' => $position ],
				[ 'id' => (int) $id ],
				[ '%d' ],
				[ '%d' ]
			);
		}

		// Flush cache after reorder.
		wp_cache_delete( 'spf_filters_all' );

		return true;
	}

	/**
	 * Processes a row from the database — decodes JSON config.
	 *
	 * @param array<string, mixed> $row Raw row from database.
	 * @return array<string, mixed>
	 */
	private static function parse_row( array $row ): array {
		$row['id']         = (int) $row['id'];
		$row['sort_order'] = (int) $row['sort_order'];
		$row['show_label'] = (bool) $row['show_label'];
		$row['config']     = json_decode( $row['config'] ?? '{}', true ) ?? [];

		return $row;
	}

	/**
	 * Deletes database tables. Called from uninstall.php.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::TABLE_FILTERS );

		delete_option( 'spf_db_version' );
		delete_option( 'spf_settings' );
	}
}
