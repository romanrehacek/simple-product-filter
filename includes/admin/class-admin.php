<?php
/**
 * Integrácia do WooCommerce Settings — dedí WC_Settings_Page.
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
 * Dedí WC_Settings_Page — využíva natívny WC systém sekcií
 * (subsubsub zoznam, $current_section, woocommerce_sections_ hook).
 */
class Admin extends \WC_Settings_Page {

	/**
	 * Konštruktor — nastaví id/label a zaregistruje hooky cez WC_Settings_Page.
	 */
	public function __construct() {
		$this->id    = 'wc_sf';
		$this->label = __( 'Filtre', 'wc-simple-filter' );

		// Rodičovský konštruktor registruje:
		// - woocommerce_settings_tabs_array (pridanie záložky)
		// - woocommerce_sections_{id}       (renderovanie subsubsub)
		// - woocommerce_settings_{id}       (renderovanie obsahu)
		// - woocommerce_settings_save_{id}  (uloženie)
		parent::__construct();

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Registruje hooky. Volá sa z Plugin::register_hooks().
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Konštruktor sa postará o WC hooky.
	}

	/**
	 * Vráti sekcie (sub-taby) pre tento settings page.
	 *
	 * @return array<string, string>
	 */
	protected function get_own_sections(): array {
		return [
			''         => __( 'Filtre', 'wc-simple-filter' ),
			'settings' => __( 'Nastavenia', 'wc-simple-filter' ),
			'help'     => __( 'Nápoveda', 'wc-simple-filter' ),
		];
	}

	/**
	 * Renderuje obsah záložky podľa aktívnej sekcie.
	 *
	 * @return void
	 */
	public function output(): void {
		global $current_section;

		// Sekcia 'edit' je špeciálna — editácia konkrétneho filtra.
		if ( 'edit' === $current_section ) {
			$filter_id = isset( $_GET['filter_id'] ) ? absint( $_GET['filter_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			( new Filter_Edit() )->render( $filter_id );
			return;
		}

		switch ( $current_section ) {
			case 'settings':
				( new Settings_Tab() )->render();
				break;

			case 'help':
				( new Help_Tab() )->render();
				break;

			default:
				( new Filters_Tab() )->render();
				break;
		}
	}

	/**
	 * Renderuje subsubsub sekcie.
	 * Pri sekcii 'edit' navigáciu skryjeme — má vlastný back link.
	 *
	 * @return void
	 */
	public function output_sections(): void {
		global $current_section;

		if ( 'edit' === $current_section ) {
			return;
		}

		parent::output_sections();
	}

	/**
	 * Uloženie — nastavenia sa ukladajú cez AJAX (wc_sf_save_settings).
	 *
	 * @return void
	 */
	public function save(): void {}

	/**
	 * Vráti base URL pre záložku.
	 *
	 * @param string $section Sekcia (prázdna = filters).
	 * @return string
	 */
	public static function tab_url( string $section = '' ): string {
		$url = admin_url( 'admin.php?page=wc-settings&tab=wc_sf' );

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
			'admin.php?page=wc-settings&tab=wc_sf&section=edit&filter_id=' . $filter_id
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

		if ( 'wc_sf' !== $tab ) {
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
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'wc_sf_admin_nonce' ),
			'i18n'        => [
				'confirmDelete' => __( 'Naozaj chcete zmazať tento filter?', 'wc-simple-filter' ),
				'saving'        => __( 'Ukladám…', 'wc-simple-filter' ),
				'saved'         => __( 'Uložené', 'wc-simple-filter' ),
				'error'         => __( 'Chyba. Skúste znova.', 'wc-simple-filter' ),
				'reindexing'    => __( 'Prebudovávam index…', 'wc-simple-filter' ),
				'reindexDone'   => __( 'Index prebudovaný.', 'wc-simple-filter' ),
				'addRangeRow'   => __( 'Pridať rozsah', 'wc-simple-filter' ),
				'removeRow'     => __( 'Odstrániť', 'wc-simple-filter' ),
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
