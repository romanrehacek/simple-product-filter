<?php
/**
 * Hlavný orchestrátor pluginu — registruje všetky hooky.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton trieda Plugin.
 *
 * Zodpovedá za načítanie tried a registráciu WordPress hookov.
 */
class Plugin {

	/**
	 * Singleton inštancia.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Privátny konštruktor — použite get_instance().
	 */
	private function __construct() {}

	/**
	 * Vráti singleton inštanciu.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Inicializuje plugin — načíta súbory a zaregistruje hooky.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->load_dependencies();
		$this->register_hooks();
	}

	/**
	 * Načíta všetky potrebné súbory.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		require_once WC_SF_PLUGIN_DIR . 'includes/class-filter-manager.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/class-index-manager.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/class-query-builder.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/class-ajax-handler.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/class-template.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/class-shortcode.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/class-frontend.php';
		// Admin triedy sa načítavajú neskôr — WC_Settings_Page musí byť dostupná.
	}

	/**
	 * Zaregistruje WordPress hooky.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// i18n.
		add_action( 'init', [ $this, 'load_textdomain' ] );

		// AJAX hooky — musia byť registrované skoro (pred wp_ajax_*).
		$ajax = new Ajax_Handler();
		$ajax->register_hooks();

		// Inkrementálny update indexu pri uložení produktu.
		$index_manager = new Index_Manager();
		$index_manager->register_hooks();

		// Frontend shortcode + assets.
		$shortcode = new Shortcode();
		$shortcode->register_hooks();

		$frontend = new Frontend();
		$frontend->register_hooks();

		// Admin UI — registrujeme cez woocommerce_get_settings_pages filter.
		// Tento filter sa spúšťa keď WC_Settings_Page je už dostupná.
		if ( is_admin() ) {
			add_filter( 'woocommerce_get_settings_pages', [ $this, 'load_admin' ] );
		}
	}

	/**
	 * Načíta admin triedy a zaregistruje Admin hooks.
	 * Volá sa cez woocommerce_get_settings_pages filter — WC_Settings_Page je v tom čase dostupná.
	 *
	 * @param array<\WC_Settings_Page> $settings Existujúce settings pages.
	 * @return array<\WC_Settings_Page>
	 */
	public function load_admin( array $settings ): array {
		require_once WC_SF_PLUGIN_DIR . 'includes/admin/class-admin.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/admin/class-filters-tab.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/admin/class-filter-edit.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/admin/class-settings-tab.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/admin/class-help-tab.php';

		$settings[] = new Admin\Admin();

		return $settings;
	}

	/**
	 * Načíta prekladový súbor pluginu.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wc-simple-filter',
			false,
			dirname( WC_SF_PLUGIN_BASENAME ) . '/languages'
		);
	}
}
