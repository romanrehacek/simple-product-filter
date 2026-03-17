<?php
/**
 * Query Builder — prekladá sanitizované filter parametre na WP_Query args.
 *
 * Táto trieda je čisto statická a nespúšťa žiadne hooky sama od seba.
 * Hookuje sa výhradne cez apply_filters() aby externe mohlo byť správanie
 * upravené bez nutnosti overridovať celú triedu.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trieda Query_Builder.
 *
 * Vstup: sanitizované pole aktívnych filter parametrov z URL ($_GET['wcsf']).
 * Výstup: pole WP_Query args pripravené na merge do hlavného query.
 */
class Query_Builder {

	/**
	 * Povolené filter_type kľúče (validácia vstupu).
	 * Dynamické typy (attribute_*, meta_*) sa kontrolujú prefix-validáciou.
	 */
	private const STATIC_TYPES = [ 'brand', 'status', 'sale', 'price' ];

	/**
	 * Povolené WooCommerce orderby hodnoty.
	 */
	public const ALLOWED_ORDERBY = [
		'menu_order',
		'popularity',
		'rating',
		'date',
		'price',
		'price-desc',
		'title',
	];

	// -------------------------------------------------------------------------
	// Verejné API
	// -------------------------------------------------------------------------

	/**
	 * Načíta, sanitizuje a vráti aktívne filter parametre z $_GET.
	 *
	 * Táto metóda je jediné miesto kde sa číta $_GET['wcsf'] — všade inde
	 * pracujeme s výstupom tejto metódy.
	 *
	 * @return array<string, mixed>  Sanitizované parametre, napr.:
	 *   [
	 *     'brand'              => ['nike', 'adidas'],
	 *     'attribute_pa_farba' => ['cervena'],
	 *     'status'             => ['instock'],
	 *     'sale'               => '1',
	 *     'price'              => ['min' => 100.0, 'max' => 500.0],
	 *   ]
	 */
	public static function get_active_params(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['wcsf'] ) || ! is_array( $_GET['wcsf'] ) ) {
			return [];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw    = (array) wp_unslash( $_GET['wcsf'] );
		$params = [];

		foreach ( $raw as $raw_key => $raw_value ) {
			$key = sanitize_key( (string) $raw_key );

			if ( empty( $key ) ) {
				continue;
			}

			// Validácia: povolené sú iba known typy + attribute_* + meta_*.
			if ( ! self::is_valid_filter_key( $key ) ) {
				continue;
			}

			// Slider — price má podkľúče min/max.
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

			// Attribute/meta slider — rovnaký vzor: wcsf[attribute_pa_xxx][min].
			if (
				is_array( $raw_value )
				&& isset( $raw_value['min'], $raw_value['max'] )
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

			// Skalárna hodnota (napr. sale=1, radio bez rozsahu).
			if ( ! is_array( $raw_value ) ) {
				$value = sanitize_text_field( (string) $raw_value );
				if ( '' !== $value ) {
					$params[ $key ] = $value;
				}
				continue;
			}

			// Pole hodnôt (checkbox multi-select, multi-dropdown).
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
	 * Zostrojí WP_Query args fragment z aktívnych filter parametrov.
	 *
	 * Vracia iba kľúče ktoré sú relevantné (tax_query, meta_query, post__in).
	 * Volajúci ich musí mergnúť do vlastného WP_Query args poľa.
	 *
	 * @param  array<string, mixed>             $params         Výstup z get_active_params().
	 * @param  array<int, array<string, mixed>> $filter_configs Filtre z DB (pre čítanie config['logic']).
	 * @return array<string, mixed>  Fragment WP_Query args.
	 */
	public static function build( array $params, array $filter_configs = [] ): array {
		if ( empty( $params ) ) {
			return [];
		}

		// Indexuj config podľa filter_type pre O(1) lookup.
		$config_index = [];
		foreach ( $filter_configs as $fc ) {
			$ft = $fc['filter_type'] ?? '';
			if ( $ft ) {
				$config_index[ $ft ] = $fc['config'] ?? [];
			}
		}

		$tax_query  = [];
		$meta_query = [];
		$post__in   = null;   // null = neaplikovať; [] = žiadne výsledky

		foreach ( $params as $filter_type => $values ) {
			$config = $config_index[ $filter_type ] ?? [];

			// -----------------------------------------------------------------
			// brand / attribute_* → tax_query
			// -----------------------------------------------------------------
			if ( 'brand' === $filter_type || str_starts_with( $filter_type, 'attribute_' ) ) {
				$tax_clause = self::build_tax_clause( $filter_type, $values, $config );
				if ( ! empty( $tax_clause ) ) {
					$tax_query[] = $tax_clause;
				}
				continue;
			}

			// -----------------------------------------------------------------
			// status → meta_query na _stock_status
			// -----------------------------------------------------------------
			if ( 'status' === $filter_type ) {
				$clause = self::build_status_clause( $values );
				if ( ! empty( $clause ) ) {
					$meta_query[] = $clause;
				}
				continue;
			}

			// -----------------------------------------------------------------
			// sale → post__in
			// -----------------------------------------------------------------
			if ( 'sale' === $filter_type ) {
				$sale_ids = self::build_sale_post_ids( $values );
				if ( null !== $post__in ) {
					// Viac post__in podmienok → prienik.
					$post__in = array_intersect( $post__in, $sale_ids );
				} else {
					$post__in = $sale_ids;
				}
				continue;
			}

			// -----------------------------------------------------------------
			// price → meta_query na _price
			// -----------------------------------------------------------------
			if ( 'price' === $filter_type ) {
				$clause = self::build_price_clause( $values );
				if ( ! empty( $clause ) ) {
					$meta_query[] = $clause;
				}
				continue;
			}

			// -----------------------------------------------------------------
			// meta_* → meta_query na custom meta kľúč
			// -----------------------------------------------------------------
			if ( str_starts_with( $filter_type, 'meta_' ) ) {
				$clause = self::build_meta_clause( $filter_type, $values, $config );
				if ( ! empty( $clause ) ) {
					$meta_query[] = $clause;
				}
				continue;
			}
		}

		// Zostrojenie finálneho args poľa.
		$args = [];

		if ( ! empty( $tax_query ) ) {
			$tax_query['relation'] = 'AND';
			/**
			 * Filter na úpravu tax_query pred aplikovaním.
			 *
			 * @param array<mixed>         $tax_query    Zostavená tax_query.
			 * @param array<string, mixed> $params       Aktívne filter parametre.
			 * @param array<mixed>         $config_index Index konfigurácií filtrov.
			 */
			$args['tax_query'] = (array) apply_filters( 'wc_sf_tax_query', $tax_query, $params, $config_index ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		if ( ! empty( $meta_query ) ) {
			$meta_query['relation'] = 'AND';
			/**
			 * Filter na úpravu meta_query pred aplikovaním.
			 *
			 * @param array<mixed>         $meta_query   Zostavená meta_query.
			 * @param array<string, mixed> $params       Aktívne filter parametre.
			 * @param array<mixed>         $config_index Index konfigurácií filtrov.
			 */
			$args['meta_query'] = (array) apply_filters( 'wc_sf_meta_query', $meta_query, $params, $config_index ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		if ( null !== $post__in ) {
			// Prázdne post__in → žiadne produkty nespĺňajú podmienku.
			$args['post__in'] = ! empty( $post__in ) ? array_values( $post__in ) : [ 0 ];
		}

		/**
		 * Filter na úpravu všetkých WP_Query args pred vrátením.
		 *
		 * @param array<string, mixed> $args    Zostavené args.
		 * @param array<string, mixed> $params  Aktívne filter parametre.
		 */
		return (array) apply_filters( 'wc_sf_query_args', $args, $params );
	}

	// -------------------------------------------------------------------------
	// Privátne builder metódy
	// -------------------------------------------------------------------------

	/**
	 * Zostrojí tax_query clause pre brand alebo attribute_* filter.
	 *
	 * @param  string              $filter_type  Typ filtra.
	 * @param  mixed               $values       Pole slugov alebo skalár.
	 * @param  array<string,mixed> $config       Config filtra z DB.
	 * @return array<string, mixed>
	 */
	private static function build_tax_clause( string $filter_type, mixed $values, array $config ): array {
		$taxonomy = 'brand' === $filter_type
			? 'product_brand'
			: 'pa_' . substr( $filter_type, strlen( 'attribute_' ) );

		// Normalizuj na pole.
		$slugs = is_array( $values ) ? $values : [ $values ];
		$slugs = array_filter( array_map( 'sanitize_text_field', $slugs ) );

		if ( empty( $slugs ) ) {
			return [];
		}

		// AND = produkt musí mať VŠETKY vybrané hodnoty; OR = aspoň jednu.
		$logic    = strtolower( $config['logic'] ?? 'or' );
		$operator = ( 'and' === $logic ) ? 'AND' : 'IN';

		$clause = [
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => array_values( $slugs ),
			'operator' => $operator,
		];

		/**
		 * Filter na úpravu jednotlivého tax_query clause.
		 *
		 * @param array<string,mixed> $clause      Clause.
		 * @param string              $filter_type Typ filtra.
		 * @param array<string>       $slugs       Vybrané hodnoty.
		 * @param array<string,mixed> $config      Config filtra.
		 */
		return (array) apply_filters( 'wc_sf_tax_query_clause', $clause, $filter_type, $slugs, $config );
	}

	/**
	 * Zostrojí meta_query clause pre status filter.
	 *
	 * @param  mixed $values  Pole stock status slugov alebo skalár.
	 * @return array<string, mixed>
	 */
	private static function build_status_clause( mixed $values ): array {
		$statuses = is_array( $values ) ? $values : [ $values ];
		$statuses = array_filter( array_map( 'sanitize_key', $statuses ) );

		if ( empty( $statuses ) ) {
			return [];
		}

		// Validácia: iba povolené WC stock status hodnoty.
		$allowed  = array_keys( wc_get_product_stock_status_options() );
		$statuses = array_values( array_intersect( $statuses, $allowed ) );

		if ( empty( $statuses ) ) {
			return [];
		}

		return [
			'key'     => '_stock_status',
			'value'   => $statuses,
			'compare' => 'IN',
		];
	}

	/**
	 * Vráti pole product IDs pre sale filter.
	 * Prázdna kolekcia (žiadne produkty v akcii) → [0] aby WP_Query vrátila 0 výsledkov.
	 *
	 * @param  mixed $values  '1' ak je filter aktívny.
	 * @return array<int>
	 */
	private static function build_sale_post_ids( mixed $values ): array {
		// Filter je aktívny ak je values '1' alebo ['on_sale'].
		$is_active = '1' === $values
			|| ( is_array( $values ) && in_array( 'on_sale', $values, true ) );

		if ( ! $is_active ) {
			return [];
		}

		$ids = wc_get_product_ids_on_sale();

		return ! empty( $ids ) ? array_map( 'absint', $ids ) : [ 0 ];
	}

	/**
	 * Zostrojí meta_query clause pre price filter.
	 *
	 * Podporuje:
	 *  - ['min' => x, 'max' => y] — slider (BETWEEN)
	 *  - ['min' => x]             — >= min
	 *  - ['max' => y]             — <= max
	 *  - pole range slugov        — OR z viacerých BETWEEN podmienok
	 *
	 * @param  mixed $values  Pole s kľúčmi min/max alebo pole range slugov.
	 * @return array<string, mixed>
	 */
	private static function build_price_clause( mixed $values ): array {
		// Slider alebo jednoduchý min/max.
		if ( is_array( $values ) && ( isset( $values['min'] ) || isset( $values['max'] ) ) ) {
			$min = isset( $values['min'] ) ? (float) $values['min'] : null;
			$max = isset( $values['max'] ) ? (float) $values['max'] : null;

			return self::numeric_range_clause( '_price', $min, $max );
		}

		// Pole range slugov napr. ['range_0_100', 'range_200_500'].
		if ( is_array( $values ) && ! empty( $values ) ) {
			$clauses = [];

			foreach ( $values as $slug ) {
				[ $min, $max ] = self::parse_range_slug( (string) $slug );
				$clause        = self::numeric_range_clause( '_price', $min, $max );

				if ( ! empty( $clause ) ) {
					$clauses[] = $clause;
				}
			}

			if ( empty( $clauses ) ) {
				return [];
			}

			// Viac rozsahov = OR (produkt musí spadať aspoň do jedného).
			if ( 1 === count( $clauses ) ) {
				return $clauses[0];
			}

			return array_merge( [ 'relation' => 'OR' ], $clauses );
		}

		return [];
	}

	/**
	 * Zostrojí meta_query clause pre meta_* filter.
	 *
	 * @param  string              $filter_type  Typ filtra (napr. 'meta_weight').
	 * @param  mixed               $values       Pole hodnôt alebo slider min/max.
	 * @param  array<string,mixed> $config       Config filtra z DB.
	 * @return array<string, mixed>
	 */
	private static function build_meta_clause( string $filter_type, mixed $values, array $config ): array {
		$meta_key = substr( $filter_type, strlen( 'meta_' ) );
		$meta_key = sanitize_key( $meta_key );

		if ( empty( $meta_key ) ) {
			return [];
		}

		// Slider — min/max.
		if ( is_array( $values ) && ( isset( $values['min'] ) || isset( $values['max'] ) ) ) {
			$min = isset( $values['min'] ) ? (float) $values['min'] : null;
			$max = isset( $values['max'] ) ? (float) $values['max'] : null;

			$clause = self::numeric_range_clause( $meta_key, $min, $max );

			/**
			 * Filter na úpravu meta_query clause pre meta_* typ.
			 *
			 * @param array<string,mixed> $clause      Clause.
			 * @param string              $filter_type Typ filtra.
			 * @param mixed               $values      Aktívne hodnoty.
			 * @param array<string,mixed> $config      Config filtra.
			 */
			return (array) apply_filters( 'wc_sf_meta_query_clause', $clause, $filter_type, $values, $config );
		}

		// Pole range slugov.
		if ( is_array( $values ) && ! empty( $values ) && str_starts_with( (string) reset( $values ), 'range_' ) ) {
			$clauses = [];

			foreach ( $values as $slug ) {
				[ $min, $max ] = self::parse_range_slug( (string) $slug );
				$clause        = self::numeric_range_clause( $meta_key, $min, $max );

				if ( ! empty( $clause ) ) {
					$clauses[] = $clause;
				}
			}

			if ( empty( $clauses ) ) {
				return [];
			}

			$result = 1 === count( $clauses )
				? $clauses[0]
				: array_merge( [ 'relation' => 'OR' ], $clauses );

			return (array) apply_filters( 'wc_sf_meta_query_clause', $result, $filter_type, $values, $config );
		}

		// Pole textových hodnôt.
		$vals = is_array( $values ) ? $values : [ $values ];
		$vals = array_filter( array_map( 'sanitize_text_field', $vals ) );

		if ( empty( $vals ) ) {
			return [];
		}

		$logic  = strtolower( $config['logic'] ?? 'or' );
		$clause = [
			'key'     => $meta_key,
			'value'   => array_values( $vals ),
			'compare' => ( 'and' === $logic ) ? 'AND' : 'IN',
		];

		return (array) apply_filters( 'wc_sf_meta_query_clause', $clause, $filter_type, $values, $config );
	}

	// -------------------------------------------------------------------------
	// Pomocné metódy
	// -------------------------------------------------------------------------

	/**
	 * Zostrojí numerický range meta_query clause.
	 *
	 * @param  string     $meta_key Meta kľúč.
	 * @param  float|null $min      Minimálna hodnota (null = bez spodnej hranice).
	 * @param  float|null $max      Maximálna hodnota (null = bez hornej hranice).
	 * @return array<string, mixed>
	 */
	private static function numeric_range_clause( string $meta_key, ?float $min, ?float $max ): array {
		if ( null === $min && null === $max ) {
			return [];
		}

		$base = [
			'key'     => $meta_key,
			'type'    => 'NUMERIC',
		];

		if ( null !== $min && null !== $max ) {
			return array_merge( $base, [
				'value'   => [ $min, $max ],
				'compare' => 'BETWEEN',
			] );
		}

		if ( null !== $min ) {
			return array_merge( $base, [
				'value'   => $min,
				'compare' => '>=',
			] );
		}

		return array_merge( $base, [
			'value'   => $max,
			'compare' => '<=',
		] );
	}

	/**
	 * Parsuje range slug na [min, max].
	 * Formát: 'range_{min}_{max}' kde max môže byť 'inf' pre neobmedzenú hornú hranicu.
	 * Príklady: 'range_0_100' → [0.0, 100.0], 'range_500_inf' → [500.0, null].
	 *
	 * @param  string $slug  Range slug.
	 * @return array{0: float|null, 1: float|null}
	 */
	private static function parse_range_slug( string $slug ): array {
		if ( ! str_starts_with( $slug, 'range_' ) ) {
			return [ null, null ];
		}

		// Odober prefix 'range_'.
		$rest  = substr( $slug, 6 );
		// Rozdelíme na max 2 časti z prava — posledná je max, predposledná min.
		$parts = explode( '_', $rest );

		if ( count( $parts ) < 2 ) {
			return [ null, null ];
		}

		$max_raw = array_pop( $parts );
		$min_raw = implode( '_', $parts );

		$min = ( '' !== $min_raw && '0' !== $min_raw ) ? (float) $min_raw : (float) $min_raw;
		$max = ( 'inf' === $max_raw || '' === $max_raw ) ? null : (float) $max_raw;

		return [ $min, $max ];
	}

	/**
	 * Validuje filter kľúč — povolené sú iba known typy.
	 *
	 * @param  string $key  Sanitizovaný kľúč.
	 * @return bool
	 */
	private static function is_valid_filter_key( string $key ): bool {
		if ( in_array( $key, self::STATIC_TYPES, true ) ) {
			return true;
		}

		if ( str_starts_with( $key, 'attribute_' ) ) {
			// attribute_ musí byť nasledovaný neprázdnym reťazcom.
			return strlen( $key ) > strlen( 'attribute_' );
		}

		if ( str_starts_with( $key, 'meta_' ) ) {
			return strlen( $key ) > strlen( 'meta_' );
		}

		return false;
	}
}
