<?php
/**
 * Template: "Settings" tab.
 *
 * Available variables:
 *   $settings    array        Settings from wp_options (wc_sf_settings)
 *   $index_time  string|null  Time of the last index rebuild
 *
 * @package WC_Simple_Filter
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
				<?php esc_html_e( 'Filtering method', 'wc-simple-filter' ); ?>
			</th>
			<td>
				<label>
					<input type="radio" name="settings[filter_mode]" value="ajax"
						<?php checked( $settings['filter_mode'] ?? 'ajax', 'ajax' ); ?> />
					<?php esc_html_e( 'AJAX — instantly without page load', 'wc-simple-filter' ); ?>
				</label>
				<br />
				<label>
					<input type="radio" name="settings[filter_mode]" value="submit"
						<?php checked( $settings['filter_mode'] ?? 'ajax', 'submit' ); ?> />
					<?php esc_html_e( 'Button — form submission', 'wc-simple-filter' ); ?>
				</label>
				<br />
				<label>
					<input type="radio" name="settings[filter_mode]" value="reload"
						<?php checked( $settings['filter_mode'] ?? 'ajax', 'reload' ); ?> />
					<?php esc_html_e( 'Reload — page refresh on every change', 'wc-simple-filter' ); ?>
				</label>
			</td>
		</tr>

		<!-- Filter button text -->
		<tr>
			<th scope="row">
				<label for="wc-sf-filter-btn-text">
					<?php esc_html_e( '"Filter" button text', 'wc-simple-filter' ); ?>
				</label>
			</th>
			<td>
				<input
					type="text"
					id="wc-sf-filter-btn-text"
					name="settings[filter_button_text]"
					value="<?php echo esc_attr( $settings['filter_button_text'] ?? __( 'Filter', 'wc-simple-filter' ) ); ?>"
					class="regular-text"
				/>
				<p class="description">
					<?php esc_html_e( 'Displayed in "Button" mode.', 'wc-simple-filter' ); ?>
				</p>
			</td>
		</tr>

		<!-- Reset button -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Reset button', 'wc-simple-filter' ); ?>
			</th>
			<td>
				<label>
					<input
						type="checkbox"
						name="settings[show_reset_button]"
						value="1"
						<?php checked( ! empty( $settings['show_reset_button'] ) ); ?>
					/>
					<?php esc_html_e( 'Display "Clear filters" button', 'wc-simple-filter' ); ?>
				</label>
			</td>
		</tr>

		<!-- Reset button text -->
		<tr>
			<th scope="row">
				<label for="wc-sf-reset-btn-text">
					<?php esc_html_e( 'Reset button text', 'wc-simple-filter' ); ?>
				</label>
			</th>
			<td>
				<input
					type="text"
					id="wc-sf-reset-btn-text"
					name="settings[reset_button_text]"
					value="<?php echo esc_attr( $settings['reset_button_text'] ?? __( 'Clear filters', 'wc-simple-filter' ) ); ?>"
					class="regular-text"
				/>
			</td>
		</tr>

		<!-- Hide empty values globally -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Hide empty values', 'wc-simple-filter' ); ?>
			</th>
			<td>
				<label>
					<input
						type="checkbox"
						name="settings[hide_empty]"
						value="1"
						<?php checked( ! empty( $settings['hide_empty'] ) ); ?>
					/>
					<?php esc_html_e( 'Do not display filter values without products (global default)', 'wc-simple-filter' ); ?>
				</label>
			</td>
		</tr>

		<!-- Delete data on uninstall -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Uninstallation', 'wc-simple-filter' ); ?>
			</th>
			<td>
				<label>
					<input
						type="checkbox"
						name="settings[delete_on_uninstall]"
						value="1"
						<?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?>
					/>
					<?php esc_html_e( 'Delete all plugin data on uninstall', 'wc-simple-filter' ); ?>
				</label>
				<p class="description wc-sf-danger-text">
					<?php esc_html_e( 'Warning: this action is irreversible. DB tables, settings, and cache will be deleted.', 'wc-simple-filter' ); ?>
				</p>
			</td>
		</tr>

	</table>

	<p class="submit">
		<button type="button" id="wc-sf-save-settings-btn" class="button button-primary">
			<?php esc_html_e( 'Save settings', 'wc-simple-filter' ); ?>
		</button>
		<span class="wc-sf-spinner spinner"></span>
		<span class="wc-sf-msg"></span>
	</p>

</div>

<hr />

<!-- Index section -->
<div class="wc-sf-index-section">
	<h3><?php esc_html_e( 'Filter value index', 'wc-simple-filter' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'The index stores product counts for each filter value. It is used to hide empty values (hide-empty).', 'wc-simple-filter' ); ?>
	</p>
	<p>
		<?php if ( $index_time ) : ?>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: time of last update */
					__( 'Last recalculated: %s', 'wc-simple-filter' ),
					$index_time
				)
			);
			?>
		<?php else : ?>
			<?php esc_html_e( 'Index has not been recalculated yet.', 'wc-simple-filter' ); ?>
		<?php endif; ?>
	</p>
	<p>
		<button type="button" id="wc-sf-reindex-btn" class="button button-secondary">
			<?php esc_html_e( 'Recalculate index', 'wc-simple-filter' ); ?>
		</button>
		<span class="wc-sf-spinner spinner"></span>
		<span class="wc-sf-msg" id="wc-sf-reindex-msg"></span>
	</p>
</div>

</div>
