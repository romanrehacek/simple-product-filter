<?php
/**
 * Template: "Settings" tab.
 *
 * Available variables:
 *   $settings    array        Settings from wp_options (spf_settings)
 *   $index_time  string|null  Time of the last index rebuild
 *
 * @package Simple_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wc-sf-wrap">

	<div id="wc-sf-settings-form">

	<table class="form-table wc-sf-form-table">

		<!-- Filtering method -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Filtering method', 'simple-product-filter' ); ?>
			</th>
			<td>
				<label>
					<input type="radio" name="settings[filter_mode]" value="ajax"
						<?php checked( $settings['filter_mode'] ?? 'ajax', 'ajax' ); ?> />
					<?php esc_html_e( 'AJAX — instantly without page load', 'simple-product-filter' ); ?>
				</label>
				<br />
				<label>
					<input type="radio" name="settings[filter_mode]" value="submit"
						<?php checked( $settings['filter_mode'] ?? 'ajax', 'submit' ); ?> />
					<?php esc_html_e( 'Button — form submission', 'simple-product-filter' ); ?>
				</label>
				<br />
				<label>
					<input type="radio" name="settings[filter_mode]" value="reload"
						<?php checked( $settings['filter_mode'] ?? 'ajax', 'reload' ); ?> />
					<?php esc_html_e( 'Reload — page refresh on every change', 'simple-product-filter' ); ?>
				</label>
			</td>
		</tr>

		<!-- Filter button text -->
		<tr>
			<th scope="row">
				<label for="wc-sf-filter-btn-text">
					<?php esc_html_e( '"Filter" button text', 'simple-product-filter' ); ?>
				</label>
			</th>
			<td>
				<input
					type="text"
					id="wc-sf-filter-btn-text"
					name="settings[filter_button_text]"
					value="<?php echo esc_attr( $settings['filter_button_text'] ?? __( 'Filter', 'simple-product-filter' ) ); ?>"
					class="regular-text"
				/>
				<p class="description">
					<?php esc_html_e( 'Displayed in "Button" mode.', 'simple-product-filter' ); ?>
				</p>
			</td>
		</tr>

		<!-- Reset button -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Reset button', 'simple-product-filter' ); ?>
			</th>
			<td>
				<label>
					<input
						type="checkbox"
						name="settings[show_reset_button]"
						value="1"
						<?php checked( ! empty( $settings['show_reset_button'] ) ); ?>
					/>
					<?php esc_html_e( 'Display "Clear filters" button', 'simple-product-filter' ); ?>
				</label>
			</td>
		</tr>

		<!-- Reset button text -->
		<tr>
			<th scope="row">
				<label for="wc-sf-reset-btn-text">
					<?php esc_html_e( 'Reset button text', 'simple-product-filter' ); ?>
				</label>
			</th>
			<td>
				<input
					type="text"
					id="wc-sf-reset-btn-text"
					name="settings[reset_button_text]"
					value="<?php echo esc_attr( $settings['reset_button_text'] ?? __( 'Clear filters', 'simple-product-filter' ) ); ?>"
					class="regular-text"
				/>
			</td>
		</tr>

		<!-- Hide empty values globally -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Hide empty values', 'simple-product-filter' ); ?>
			</th>
			<td>
				<label>
					<input
						type="checkbox"
						name="settings[hide_empty]"
						value="1"
						<?php checked( ! empty( $settings['hide_empty'] ) ); ?>
					/>
					<?php esc_html_e( 'Do not display filter values without products (global default)', 'simple-product-filter' ); ?>
				</label>
			</td>
		</tr>

		<!-- Delete data on uninstall -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Uninstallation', 'simple-product-filter' ); ?>
			</th>
			<td>
				<label>
					<input
						type="checkbox"
						name="settings[delete_on_uninstall]"
						value="1"
						<?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?>
					/>
					<?php esc_html_e( 'Delete all plugin data on uninstall', 'simple-product-filter' ); ?>
				</label>
				<p class="description wc-sf-danger-text">
					<?php esc_html_e( 'Warning: this action is irreversible. DB tables, settings, and cache will be deleted.', 'simple-product-filter' ); ?>
				</p>
			</td>
		</tr>

	</table>

	<p class="submit">
		<button type="button" id="wc-sf-save-settings-btn" class="button button-primary">
			<?php esc_html_e( 'Save settings', 'simple-product-filter' ); ?>
		</button>
		<span class="wc-sf-spinner spinner"></span>
		<span class="wc-sf-msg"></span>
	</p>

</div>

<hr />

<!-- Index section -->
<div class="wc-sf-index-section">
	<h3><?php esc_html_e( 'Filter value index', 'simple-product-filter' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'The index stores product counts for each filter value. It is used to hide empty values (hide-empty).', 'simple-product-filter' ); ?>
	</p>
	<p>
		<?php if ( $index_time ) : ?>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: time of last update */
					__( 'Last recalculated: %s', 'simple-product-filter' ),
					$index_time
				)
			);
			?>
		<?php else : ?>
			<?php esc_html_e( 'Index has not been recalculated yet.', 'simple-product-filter' ); ?>
		<?php endif; ?>
	</p>
	<p>
		<button type="button" id="wc-sf-reindex-btn" class="button button-secondary">
			<?php esc_html_e( 'Recalculate index', 'simple-product-filter' ); ?>
		</button>
		<span class="wc-sf-spinner spinner"></span>
		<span class="wc-sf-msg" id="wc-sf-reindex-msg"></span>
	</p>
</div>

</div>
