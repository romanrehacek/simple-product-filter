<?php
/**
 * Správa filtrov — CRUD operácie a inštalácia DB.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trieda Filter_Manager.
 *
 * Zodpovedá za:
 * - vytvorenie DB tabuliek pri aktivácii
 * - CRUD operácie nad tabuľkou wc_sf_filters
 */
class Filter_Manager {

	/**
	 * Názov tabuľky filtrov (bez prefixu).
	 */
	const TABLE_FILTERS = 'wc_sf_filters';

	/**
	 * Verzia DB schémy.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Vytvorí DB tabuľky. Volá sa pri aktivácii pluginu.
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

		// Vytvor index tabuľku.
		require_once WC_SF_PLUGIN_DIR . 'includes/class-index-manager.php';
		Index_Manager::install();

		// Ulož verziu schémy.
		update_option( 'wc_sf_db_version', self::DB_VERSION );

		// Ulož default nastavenia ak ešte neexistujú.
		if ( false === get_option( 'wc_sf_settings' ) ) {
			add_option( 'wc_sf_settings', self::default_settings() );
		}
	}

	/**
	 * Vráti default nastavenia pluginu.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return [
			'filter_mode'          => 'ajax',
			'filter_button_text'   => __( 'Filtrovať', 'wc-simple-filter' ),
			'show_reset_button'    => true,
			'reset_button_text'    => __( 'Zrušiť filtre', 'wc-simple-filter' ),
			'hide_empty'           => true,
			'delete_on_uninstall'  => false,
		];
	}

	/**
	 * Vráti všetky filtre zoradené podľa sort_order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all(): array {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_FILTERS;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC", ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map( [ self::class, 'parse_row' ], $rows );
	}

	/**
	 * Vráti jeden filter podľa ID.
	 *
	 * @param int $id ID filtra.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_FILTERS;
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		return self::parse_row( $row );
	}

	/**
	 * Vytvorí nový filter.
	 *
	 * @param array<string, mixed> $data Dáta filtra.
	 * @return int|false ID nového filtra alebo false pri chybe.
	 */
	public static function create( array $data ): int|false {
		global $wpdb;

		$table      = $wpdb->prefix . self::TABLE_FILTERS;
		$max_order  = (int) $wpdb->get_var( "SELECT MAX(sort_order) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$insert = [
			'sort_order'   => $max_order + 1,
			'filter_type'  => sanitize_text_field( $data['filter_type'] ?? '' ),
			'filter_style' => sanitize_text_field( $data['filter_style'] ?? 'checkbox' ),
			'label'        => sanitize_text_field( $data['label'] ?? '' ),
			'show_label'   => isset( $data['show_label'] ) ? (int) $data['show_label'] : 1,
			'config'       => wp_json_encode( $data['config'] ?? new \stdClass() ),
		];

		$result = $wpdb->insert( $table, $insert );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Aktualizuje existujúci filter.
	 *
	 * @param int                  $id   ID filtra.
	 * @param array<string, mixed> $data Nové dáta.
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

		$result = $wpdb->update(
			$table,
			$update,
			[ 'id' => $id ]
		);

		return false !== $result;
	}

	/**
	 * Zmaže filter podľa ID.
	 *
	 * @param int $id ID filtra.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		$table  = $wpdb->prefix . self::TABLE_FILTERS;
		$result = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	/**
	 * Aktualizuje poradie filtrov.
	 *
	 * @param int[] $ordered_ids Pole ID v novom poradí.
	 * @return bool
	 */
	public static function reorder( array $ordered_ids ): bool {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_FILTERS;

		foreach ( $ordered_ids as $position => $id ) {
			$wpdb->update(
				$table,
				[ 'sort_order' => $position ],
				[ 'id' => (int) $id ],
				[ '%d' ],
				[ '%d' ]
			);
		}

		return true;
	}

	/**
	 * Spracuje riadok z DB — dekóduje JSON config.
	 *
	 * @param array<string, mixed> $row Surový riadok z DB.
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
	 * Zmaže DB tabuľky. Volá sa z uninstall.php.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::TABLE_FILTERS ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		delete_option( 'wc_sf_db_version' );
		delete_option( 'wc_sf_settings' );
	}
}
