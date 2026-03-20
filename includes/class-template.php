<?php
/**
 * Template loader with WooCommerce-style override system.
 *
 * Templates can be overridden from a theme:
 *   {theme}/wc-simple-filter/{template}.php
 *   or from the plugin directory:
 *   {plugin}/templates/frontend/{template}.php
 *
 * @package Simple_Product_Filter
 */

namespace Simple_Product_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Template.
 *
 * Responsible for locating and loading frontend templates
 * with support for theme overrides (like WooCommerce templates/).
 */
class Template {

	/**
	 * Template directory in theme (relative to theme root).
	 */
	const THEME_DIR = 'simple-product-filter';

	/**
	 * Template directory in plugin (relative to plugin root).
	 */
	const PLUGIN_DIR = 'templates/frontend/';

	/**
	 * Returns the path to a template with lookup: theme → child-theme → plugin.
	 *
	 * @param string $template_name Relative path to template, e.g., 'filter-item.php'.
	 * @return string Absolute path to template.
	 */
	public static function locate( string $template_name ): string {
		$template = '';

		// Search in current theme and child theme.
		$theme_locations = [
			get_stylesheet_directory() . '/' . self::THEME_DIR . '/' . $template_name,
			get_template_directory() . '/' . self::THEME_DIR . '/' . $template_name,
		];

		foreach ( $theme_locations as $location ) {
			if ( file_exists( $location ) ) {
				$template = $location;
				break;
			}
		}

		// Fallback to plugin template.
		if ( ! $template ) {
			$plugin_template = SPF_PLUGIN_DIR . self::PLUGIN_DIR . $template_name;
			if ( file_exists( $plugin_template ) ) {
				$template = $plugin_template;
			}
		}

		/**
		 * Filter to override template path.
		 *
		 * @param string $template      Absolute path to template.
		 * @param string $template_name Relative template path.
		 */
		return (string) apply_filters( 'spf_locate_template', $template, $template_name );
	}

	/**
	 * Loads and displays a template.
	 *
	 * @param string               $template_name Relative path to template, e.g., 'filter-item.php'.
	 * @param array<string, mixed> $args          Variables available in the template.
	 * @param bool                 $return        If true, return HTML as string instead of echo.
	 * @return string HTML output (only if $return === true).
	 */
	public static function get_template( string $template_name, array $args = [], bool $return = false ): string {
		$template = self::locate( $template_name );

		if ( ! $template ) {
			return '';
		}

		/**
		 * Action before loading template.
		 *
		 * @param string               $template_name Relative template path.
		 * @param array<string, mixed> $args          Template variables.
		 */
		do_action( 'spf_before_template', $template_name, $args );

		if ( $return ) {
			ob_start();
		}

		// Safe extract — variables available in template.
		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args, EXTR_SKIP );
		}

		include $template;

		/**
		 * Action after loading template.
		 *
		 * @param string               $template_name Relative template path.
		 * @param array<string, mixed> $args          Template variables.
		 */
		do_action( 'spf_after_template', $template_name, $args );

		if ( $return ) {
			return (string) ob_get_clean();
		}

		return '';
	}

	/**
	 * Returns the URL of the theme template directory (for documentation / debug).
	 *
	 * @return string URL of the theme template directory.
	 */
	public static function get_theme_template_dir_url(): string {
		return get_stylesheet_directory_uri() . '/' . self::THEME_DIR . '/';
	}

	/**
	 * Returns the URL of the plugin template directory.
	 *
	 * @return string URL of the plugin template directory.
	 */
	public static function get_plugin_template_dir_url(): string {
		return SPF_PLUGIN_URL . self::PLUGIN_DIR;
	}
}
