<?php
/**
 * Integrácia do WooCommerce Settings — hlavná záložka a sub-tab navigácia.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Simple_Filter\Filter_Manager;

/**
 * Trieda Admin.
 *
 * Zodpovedá za:
 * - pridanie záložky do WC Settings
 * - renderovanie správnej sekcie podľa URL parametra ?section=
 * - enqueue admin assets
 */
class Admin {

	/**
	 * Slug hlavnej WC Settings záložky.
	 */
	const TAB_SLUG = 'wc_sf';

	/**
	 * Zaregistruje WordPress hooky.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Pridanie záložky do WC Settings.
		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_tab' ], 50 );

		// Render obsahu záložky.
		add_action( 'woocommerce_settings_' . self::TAB_SLUG, [ $this, 'render_tab' ] );

		// Enqueue admin assets.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Pridá záložku „Filtre" do WC Settings.
	 *
	 * @param array<string, string> $tabs Existujúce záložky.
	 * @return array<string, string>
	 */
	public function add_settings_tab( array $tabs ): array {
		$tabs[ self::TAB_SLUG ] = __( 'Filtre', 'wc-simple-filter' );
		return $tabs;
	}

	/**
	 * Renderuje obsah záložky podľa aktívnej sekcie.
	 *
	 * @return void
	 */
	public function render_tab(): void {
		$section = $this->get_current_section();

		// Sub-tab navigácia.
		$this->render_subnav( $section );

		// Renderovanie správnej sekcie.
		switch ( $section ) {
			case 'settings':
				( new Settings_Tab() )->render();
				break;

			case 'help':
				( new Help_Tab() )->render();
				break;

			case 'edit':
				$filter_id = isset( $_GET['filter_id'] ) ? absint( $_GET['filter_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				( new Filter_Edit() )->render( $filter_id );
				break;

			default:
				( new Filters_Tab() )->render();
				break;
		}
	}

	/**
	 * Renderuje sub-tab navigáciu (skryje pri editácii filtra).
	 *
	 * @param string $active_section Aktívna sekcia.
	 * @return void
	 */
	private function render_subnav( string $active_section ): void {
		// Pri editácii konkrétneho filtra skryjeme navigáciu — má vlastný back link.
		if ( 'edit' === $active_section ) {
			return;
		}

		$tabs = [
			''         => __( 'Filtre', 'wc-simple-filter' ),
			'settings' => __( 'Nastavenia', 'wc-simple-filter' ),
			'help'     => __( 'Nápoveda', 'wc-simple-filter' ),
		];

		$base_url = admin_url( 'admin.php?page=wc-settings&tab=' . self::TAB_SLUG );

		echo '<nav class="nav-tab-wrapper woo-nav-tab-wrapper">';

		foreach ( $tabs as $slug => $label ) {
			$url        = $slug ? $base_url . '&section=' . $slug : $base_url;
			$is_active  = ( '' === $slug && '' === $active_section )
				|| ( $slug && $slug === $active_section );
			$class      = 'nav-tab' . ( $is_active ? ' nav-tab-active' : '' );

			printf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}

		echo '</nav>';
	}

	/**
	 * Vráti aktívnu sekciu z URL.
	 *
	 * @return string
	 */
	public static function get_current_section(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : '';
	}

	/**
	 * Vráti base URL pre záložku.
	 *
	 * @param string $section Sekcia (prázdna = filters).
	 * @return string
	 */
	public static function tab_url( string $section = '' ): string {
		$url = admin_url( 'admin.php?page=wc-settings&tab=' . self::TAB_SLUG );

		if ( $section ) {
			$url .= '&section=' . rawurlencode( $section );
		}

		return $url;
	}

	/**
	 * Vráti URL pre editáciu konkrétneho filtra.
	 *
	 * @param int $filter_id ID filtra.
	 * @return string
	 */
	public static function filter_edit_url( int $filter_id ): string {
		return admin_url(
			'admin.php?page=wc-settings&tab=' . self::TAB_SLUG
			. '&section=edit&filter_id=' . $filter_id
		);
	}

	/**
	 * Enqueue admin CSS a JS — iba na stránkach tohto pluginu.
	 *
	 * @param string $hook_suffix Hook suffix aktuálnej admin stránky.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';

		if ( self::TAB_SLUG !== $tab ) {
			return;
		}

		wp_enqueue_style(
			'wc-sf-admin',
			WC_SF_PLUGIN_URL . 'assets/css/admin.css',
			[],
			WC_SF_VERSION
		);

		wp_enqueue_script(
			'wc-sf-admin',
			WC_SF_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			WC_SF_VERSION,
			true
		);

		wp_localize_script( 'wc-sf-admin', 'wcSfAdmin', [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'wc_sf_admin_nonce' ),
			'i18n'      => [
				'confirmDelete'    => __( 'Naozaj chcete zmazať tento filter?', 'wc-simple-filter' ),
				'saving'           => __( 'Ukladám…', 'wc-simple-filter' ),
				'saved'            => __( 'Uložené', 'wc-simple-filter' ),
				'error'            => __( 'Chyba. Skúste znova.', 'wc-simple-filter' ),
				'reindexing'       => __( 'Prebudovávam index…', 'wc-simple-filter' ),
				'reindexDone'      => __( 'Index prebudovaný.', 'wc-simple-filter' ),
				'addRangeRow'      => __( 'Pridať rozsah', 'wc-simple-filter' ),
				'removeRow'        => __( 'Odstrániť', 'wc-simple-filter' ),
			],
			'filterTypes' => $this->get_filter_types_for_js(),
		] );
	}

	/**
	 * Vráti zoznam typov filtrov + povolené štýly pre JS.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_filter_types_for_js(): array {
		$types = [
			'brand' => [
				'label'       => __( 'Značka', 'wc-simple-filter' ),
				'fixed_style' => false,
				'styles'      => [ 'checkbox', 'radio', 'dropdown', 'multi_dropdown' ],
			],
			'status' => [
				'label'       => __( 'Stav skladu', 'wc-simple-filter' ),
				'fixed_style' => 'checkbox',
				'styles'      => [ 'checkbox' ],
			],
			'sale' => [
				'label'       => __( 'Zľava', 'wc-simple-filter' ),
				'fixed_style' => 'checkbox',
				'styles'      => [ 'checkbox' ],
			],
			'price' => [
				'label'       => __( 'Cena', 'wc-simple-filter' ),
				'fixed_style' => false,
				'styles'      => [ 'checkbox', 'radio', 'slider' ],
			],
		];

		// WC atribúty.
		$attributes = wc_get_attribute_taxonomies();

		if ( is_array( $attributes ) ) {
			foreach ( $attributes as $attr ) {
				$key           = 'attribute_pa_' . $attr->attribute_name;
				$types[ $key ] = [
					'label'       => $attr->attribute_label,
					'fixed_style' => false,
					'styles'      => [ 'checkbox', 'radio', 'dropdown', 'multi_dropdown', 'slider' ],
					'is_numeric'  => false,
				];
			}
		}

		return $types;
	}
}
