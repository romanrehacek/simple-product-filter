=== WC Simple Filter ===
Contributors: romanrehacek
Donate link:
Tags: woocommerce, product filter, ajax filter, filter products, woo commerce
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, configurable product filter plugin for WooCommerce shops and product archives. Create and manage custom filters without modifying products.

== Description ==

WC Simple Filter enables shop managers to add powerful, customizable product filters to their WooCommerce stores with minimal effort. Filters can be displayed via shortcode `[wc_simple_filter]` or the PHP helper function `wc_simple_filter()`.

This plugin provides an intuitive interface for creating and managing product filters without requiring code modifications or product edits. It's built with performance in mind, using efficient database indexing for fast filter queries.

= Key Features =

* **Configurable Filters** - Create filters for product categories, tags, attributes, and custom taxonomies
* **Multiple Filter Types** - Support for checkbox, radio, dropdown, and range-based filters
* **Hide Empty Values** - Automatically hide filter values with no matching products
* **Performance Optimized** - Efficient database indexing for fast filter queries
* **Read-Only** - Never modifies or deletes WooCommerce products
* **Easy Integration** - Simple shortcode or PHP function integration
* **Admin Interface** - Intuitive settings within WooCommerce admin
* **Internationalization** - Full i18n support with translation-ready code
* **WordPress Multisite** - Full support for multisite installations

== Installation ==

1. Download the plugin from WordPress.org or install directly from your WordPress admin
2. Activate "WC Simple Filter" from the Plugins menu
3. Navigate to **WooCommerce > Settings > Filters** to configure your filters
4. Use the shortcode `[wc_simple_filter]` on any page or post where you want filters to appear

== Usage ==

= Using the Shortcode =

Add this shortcode to any page, post, or template:

`[wc_simple_filter]`

= Using PHP Function =

Add this function to your theme template or custom plugin:

`<?php
if ( function_exists( 'wc_simple_filter' ) ) {
    wc_simple_filter();
}
?>`

= Configuration =

1. Go to **WooCommerce > Settings > Filters**
2. Click **Add Filter** to create a new filter
3. Configure:
   - Filter Name: Display label for the filter
   - Filter Type: Choose from available types (categories, tags, attributes, etc.)
   - Style: Select visual style (checkbox, radio, dropdown, etc.)
   - Options: Set specific values or auto-detect from products
4. Save and activate the filter

== Frequently Asked Questions ==

= What are the system requirements? =

This plugin requires:
* WordPress 6.0 or higher
* WooCommerce 7.0 or higher
* PHP 8.0 or higher

= Will this plugin modify my products? =

No. WC Simple Filter is read-only and never modifies or deletes any WooCommerce products or product data.

= Can I use this on a multisite network? =

Yes, the plugin has full support for WordPress multisite installations.

= How do I customize the filter appearance? =

You can customize the CSS by editing the plugin's CSS files in the `assets/css/` directory, or by adding custom CSS to your theme's stylesheet.

= What happens if I deactivate the plugin? =

When you deactivate the plugin, all filters will stop displaying on your website. Upon reactivation, your filter configuration will be preserved.

= Is the plugin translation-ready? =

Yes, the plugin is fully translation-ready with `.pot` files included for translating into other languages.

== Screenshots ==

1. `screenshot-1.png` – Filter creator where users pick and combine the preferred filters.
2. `screenshot-2.png` – Brand filter settings allowing you to include or exclude specific manufacturers.
3. `screenshot-3.png` – Product stock status filter configuration with support for custom statuses.
4. `screenshot-4.png` – Price filter presented as checkboxes for predefined price ranges.
5. `screenshot-5.png` – Price filter in slider mode for granular range selection.
6. `screenshot-6.png` – General plugin settings screen with display, behavior, and performance controls.
7. `screenshot-7.png` – Built-in help page guiding users through setup and advanced features.

== Changelog ==

= 0.1.0 =
* Initial plugin release
* Core filter configuration system
* Database schema creation
* Admin settings interface
* Frontend rendering with AJAX support
* Support for categories, tags, attributes, and custom taxonomies

== Upgrade Notice ==

= 0.1.0 =
Initial release of WC Simple Filter. Install and activate to start creating product filters.

== Support ==

For issues, feature requests, or support, please visit the [GitHub repository](https://github.com/romanrehacek/wc-simple-filter/issues).

== License ==

This plugin is licensed under the GPL v2 or later. See LICENSE file for details.

== Credits ==

Developed by Roman Rehacek

== Technical Details ==

= Database Tables =

The plugin creates two custom tables:
* `{prefix}wc_sf_filters` - Stores filter configurations
* `{prefix}wc_sf_index` - Stores product count index for performance

These tables are created automatically on plugin activation and cleaned up on uninstall.

= Hooks & Filters =

* `wc_sf_before_filters` - Fired before filters render on frontend
* `wc_sf_after_filters` - Fired after filters render on frontend
* `wc_sf_filter_registered` - Fired when a new filter is saved
* `wc_sf_filter_output` - Modifies filter HTML output
* `wc_sf_filter_config` - Modifies filter configuration before rendering
* `wc_sf_index_count` - Customizes product count calculations

= Security =

* Input Sanitization - All user inputs are sanitized
* Output Escaping - All output is properly escaped
* Nonce Verification - Nonces protect all form submissions
* SQL Injections - All database queries use prepared statements
* Capability Checks - Only authenticated users with proper permissions
