<?php
/**
 * Template: "Help" tab.
 *
 * @package Simple_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wc-sf-wrap wc-sf-help-wrap">

	<h2><?php esc_html_e( 'How to use WC Simple Filter', 'simple-product-filter' ); ?></h2>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Inserting via shortcode', 'simple-product-filter' ); ?></h3>
		<p>
			<?php esc_html_e( 'Insert the shortcode on any page or in a widget:', 'simple-product-filter' ); ?>
		</p>
		<pre class="wc-sf-code"><code>[spf]</code></pre>
	</div>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Inserting directly in PHP template', 'simple-product-filter' ); ?></h3>
		<p>
			<?php esc_html_e( 'If you prefer inserting directly in the theme PHP file:', 'simple-product-filter' ); ?>
		</p>
		<pre class="wc-sf-code"><code>&lt;?php spf(); ?&gt;</code></pre>
	</div>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Filter types', 'simple-product-filter' ); ?></h3>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'simple-product-filter' ); ?></th>
					<th><?php esc_html_e( 'Description', 'simple-product-filter' ); ?></th>
					<th><?php esc_html_e( 'Available styles', 'simple-product-filter' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>brand</code></td>
					<td><?php esc_html_e( 'Filter by brand (taxonomy product_brand)', 'simple-product-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox, radio, dropdown, multi-dropdown', 'simple-product-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>status</code></td>
					<td><?php esc_html_e( 'Stock status (in stock, out of stock, on backorder)', 'simple-product-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox (fixed)', 'simple-product-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>sale</code></td>
					<td><?php esc_html_e( 'Sale products (on sale / not on sale)', 'simple-product-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox (fixed)', 'simple-product-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>price</code></td>
					<td><?php esc_html_e( 'Price filter — ranges or slider', 'simple-product-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox, radio, slider', 'simple-product-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>attribute_{slug}</code></td>
					<td><?php esc_html_e( 'WooCommerce product attribute (e.g., attribute_pa_color)', 'simple-product-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox, radio, dropdown, multi-dropdown, slider', 'simple-product-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>meta_{key}</code></td>
					<td><?php esc_html_e( 'Custom meta field (configured by admin — e.g., meta_weight)', 'simple-product-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox, radio, dropdown, multi-dropdown, slider', 'simple-product-filter' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Value index (hide-empty)', 'simple-product-filter' ); ?></h3>
		<p>
			<?php esc_html_e( 'The plugin maintains an internal index of product counts for each filter value. This index is automatically updated when a product is saved. If you see outdated values, you can manually recalculate it in the Settings tab.', 'simple-product-filter' ); ?>
		</p>
	</div>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Planned features (Phase 2)', 'simple-product-filter' ); ?></h3>
		<ul class="ul-disc">
			<li><?php esc_html_e( 'AJAX product filtering without page reload', 'simple-product-filter' ); ?></li>
			<li><?php esc_html_e( 'URL state — sharing and back/forward navigation', 'simple-product-filter' ); ?></li>
			<li><?php esc_html_e( 'Frontend CSS styling', 'simple-product-filter' ); ?></li>
			<li><?php esc_html_e( 'WooCommerce query integration', 'simple-product-filter' ); ?></li>
		</ul>
	</div>

</div>
