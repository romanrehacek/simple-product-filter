<?php
/**
 * Main plugin orchestrator — registers all hooks.
 *
 * @package Simple_Product_Filter
 */

namespace Simple_Product_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin singleton class.
 *
 * Responsible for loading classes and registering WordPress hooks.
 */
class Plugin {

	/**
	 * Singleton inštancia.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {}

	/**
	 * Returns the singleton instance.
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
	 * Initializes the plugin — loads files and registers hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->load_dependencies();
		$this->register_hooks();
	}

	/**
	 * Loads all required files.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		require_once SPF_PLUGIN_DIR . 'includes/class-filter-manager.php';
		require_once SPF_PLUGIN_DIR . 'includes/class-index-manager.php';
		require_once SPF_PLUGIN_DIR . 'includes/class-query-builder.php';
		require_once SPF_PLUGIN_DIR . 'includes/class-ajax-handler.php';
		require_once SPF_PLUGIN_DIR . 'includes/class-template.php';
		require_once SPF_PLUGIN_DIR . 'includes/class-shortcode.php';
		require_once SPF_PLUGIN_DIR . 'includes/class-frontend.php';
		// Admin classes are loaded later — WC_Settings_Page must be available.
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// i18n.
		add_action( 'init', [ $this, 'load_textdomain' ] );

		// AJAX hooks — must be registered early (before wp_ajax_*).
		$ajax = new Ajax_Handler();
		$ajax->register_hooks();

		// Incremental index update on product save.
		$index_manager = new Index_Manager();
		$index_manager->register_hooks();

		// Frontend shortcode + assets.
		$shortcode = new Shortcode();
		$shortcode->register_hooks();

		$frontend = new Frontend();
		$frontend->register_hooks();

		// Admin UI — register via woocommerce_get_settings_pages filter.
		// This filter is fired when WC_Settings_Page is available.
		if ( is_admin() ) {
			add_filter( 'woocommerce_get_settings_pages', [ $this, 'load_admin' ] );
		}
	}

	/**
	 * Loads admin classes and registers admin hooks.
	 * Called via woocommerce_get_settings_pages filter — WC_Settings_Page is available at that point.
	 *
	 * @param array<\WC_Settings_Page> $settings Existing settings pages.
	 * @return array<\WC_Settings_Page>
	 */
	public function load_admin( array $settings ): array {
		require_once SPF_PLUGIN_DIR . 'includes/admin/class-admin.php';
		require_once SPF_PLUGIN_DIR . 'includes/admin/class-filters-tab.php';
		require_once SPF_PLUGIN_DIR . 'includes/admin/class-filter-edit.php';
		require_once SPF_PLUGIN_DIR . 'includes/admin/class-settings-tab.php';
		require_once SPF_PLUGIN_DIR . 'includes/admin/class-help-tab.php';

		$settings[] = new Admin\Admin();

		return $settings;
	}

	/**
	 * Placeholder i18n callback kept for init hook compatibility.
	 * WordPress.org language packs are loaded automatically.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		// Intentionally left blank.
	}

}
