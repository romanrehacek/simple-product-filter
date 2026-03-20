<?php
/**
 * Integration into WooCommerce Settings — inherits WC_Settings_Page.
 *
 * @package Simple_Product_Filter
 */

namespace Simple_Product_Filter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Simple_Product_Filter\Filter_Manager;

/**
 * Admin class.
 *
 * Inherits WC_Settings_Page — uses the native WC system of sections
 * (subsubsub list, $current_section, woocommerce_sections_ hook).
 */
class Admin extends \WC_Settings_Page {

	/**
	 * Constructor — sets id/label and registers hooks through WC_Settings_Page.
	 */
	public function __construct() {
		$this->id    = 'wc_sf';
		$this->label = __( 'Filters', 'simple-product-filter' );

		// Parent constructor registers:
		// - woocommerce_settings_tabs_array (add tab)
		// - woocommerce_sections_{id}       (render subsubsub)
		// - woocommerce_settings_{id}       (render content)
		// - woocommerce_settings_save_{id}  (save)
		parent::__construct();

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Registers hooks. Called from Plugin::register_hooks().
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Constructor will handle WC hooks.
	}

	/**
	 * Returns sections (sub-tabs) for this settings page.
	 *
	 * @return array<string, string>
	 */
	protected function get_own_sections(): array {
		return [
			''         => __( 'Filters', 'simple-product-filter' ),
			'settings' => __( 'Settings', 'simple-product-filter' ),
			'help'     => __( 'Help', 'simple-product-filter' ),
		];
	}

	/**
	 * Renders tab content according to the active section.
	 *
	 * @return void
	 */
	public function output(): void {
		global $current_section;

		// The 'edit' section is special — editing a specific filter.
		if ( 'edit' === $current_section ) {
			$GLOBALS['hide_save_button'] = true;
			$filter_id = isset( $_GET['filter_id'] ) ? absint( $_GET['filter_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			( new Filter_Edit() )->render( $filter_id );
			return;
		}

		switch ( $current_section ) {
			case 'settings':
				// Settings have their own AJAX saving — we don't need the WC button.
				$GLOBALS['hide_save_button'] = true;
				( new Settings_Tab() )->render();
				break;

			case 'help':
				$GLOBALS['hide_save_button'] = true;
				( new Help_Tab() )->render();
				break;

			default:
				// Filters tab — list of filters, no saving via WC mainform.
				$GLOBALS['hide_save_button'] = true;
				( new Filters_Tab() )->render();
				break;
		}
	}

	/**
	 * Renders subsubsub sections.
	 * When the 'edit' section is active, we hide the navigation — it has its own back link.
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
	 * Saving — settings are saved via AJAX (spf_save_settings).
	 *
	 * @return void
	 */
	public function save(): void {}

	/**
	 * Returns the base URL for the tab.
	 *
	 * @param string $section Section (empty = filters).
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
	 * Returns the URL for editing a specific filter.
	 *
	 * @param int $filter_id Filter ID.
	 * @return string
	 */
	public static function filter_edit_url( int $filter_id ): string {
		return admin_url(
			'admin.php?page=wc-settings&tab=wc_sf&section=edit&filter_id=' . $filter_id
		);
	}

	/**
	 * Enqueue admin CSS and JS — only on plugin pages.
	 *
	 * @param string $hook_suffix Hook suffix of the current admin page.
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
			'spf-admin',
			SPF_PLUGIN_URL . 'assets/css/admin.css',
			[],
			SPF_VERSION
		);

		wp_enqueue_script(
			'spf-admin',
			SPF_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			SPF_VERSION,
			true
		);

		wp_localize_script( 'spf-admin', 'spfAdmin', [
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'spf_admin_nonce' ),
			'i18n'        => [
				'confirmDelete' => __( 'Are you sure you want to delete this filter?', 'simple-product-filter' ),
				'saving'        => __( 'Saving…', 'simple-product-filter' ),
				'saved'         => __( 'Saved', 'simple-product-filter' ),
				'error'         => __( 'Error. Try again.', 'simple-product-filter' ),
				'reindexing'    => __( 'Rebuilding index…', 'simple-product-filter' ),
				'reindexDone'   => __( 'Index rebuilt.', 'simple-product-filter' ),
				'addRangeRow'   => __( 'Add range', 'simple-product-filter' ),
				'removeRow'     => __( 'Remove', 'simple-product-filter' ),
			],
			'filterTypes' => $this->get_filter_types_for_js(),
		] );
	}

	/**
	 * Returns a list of filter types + allowed styles for JS.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_filter_types_for_js(): array {
		$types = [
			'brand' => [
				'label'       => __( 'Brand', 'simple-product-filter' ),
				'fixed_style' => false,
				'styles'      => [ 'checkbox', 'radio', 'dropdown', 'multi_dropdown' ],
			],
			'status' => [
				'label'       => __( 'Stock status', 'simple-product-filter' ),
				'fixed_style' => 'checkbox',
				'styles'      => [ 'checkbox' ],
			],
			'sale' => [
				'label'       => __( 'Sale', 'simple-product-filter' ),
				'fixed_style' => 'checkbox',
				'styles'      => [ 'checkbox' ],
			],
			'price' => [
				'label'       => __( 'Price', 'simple-product-filter' ),
				'fixed_style' => false,
				'styles'      => [ 'checkbox', 'radio', 'slider' ],
			],
		];

		// WC attributes.
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
