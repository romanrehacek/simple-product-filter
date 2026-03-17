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
		require_once WC_SF_PLUGIN_DIR . 'includes/class-ajax-handler.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/admin/class-admin.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/admin/class-filters-tab.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/admin/class-filter-edit.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/admin/class-settings-tab.php';
		require_once WC_SF_PLUGIN_DIR . 'includes/admin/class-help-tab.php';
	}

	/**
	 * Zaregistruje WordPress hooky.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// i18n.
		add_action( 'init', [ $this, 'load_textdomain' ] );

		// Admin UI.
		if ( is_admin() ) {
			$admin = new Admin\Admin();
			$admin->register_hooks();

			$ajax = new Ajax_Handler();
			$ajax->register_hooks();

			// Inkrementálny update indexu pri uložení produktu.
			$index_manager = new Index_Manager();
			$index_manager->register_hooks();
		}
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
