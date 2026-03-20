<?php
/**
 * Template: Filter editing page.
 *
 * Available variables:
 *   $filter  array  Filter data from Filter_Manager::get()
 *
 * @package Simple_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Simple_Product_Filter\Admin\Admin;

$config       = $filter['config'] ?? [];
$filter_type  = $filter['filter_type'] ?? '';
$filter_style = $filter['filter_style'] ?? 'checkbox';

// Determine filter group.
$is_fixed_style  = in_array( $filter_type, [ 'status', 'sale' ], true );
$is_taxonomy     = in_array( $filter_type, [ 'brand' ], true ) || str_starts_with( $filter_type, 'attribute_' );
$is_meta         = str_starts_with( $filter_type, 'meta_' );
$is_price        = 'price' === $filter_type;
$is_status       = 'status' === $filter_type;
$is_slider       = 'slider' === $filter_style;
$has_ranges      = isset( $config['ranges'] ) && is_array( $config['ranges'] );

// For price with checkbox/radio section ranges is always visible.
$show_ranges = $is_price && ! $is_slider;

$style_labels = [
	'checkbox'       => __( 'Checkboxes', 'simple-product-filter' ),
	'radio'          => __( 'Radio buttons', 'simple-product-filter' ),
	'dropdown'       => __( 'Dropdown', 'simple-product-filter' ),
	'multi_dropdown' => __( 'Multi-dropdown', 'simple-product-filter' ),
	'slider'         => __( 'Slider', 'simple-product-filter' ),
];

// Allowed styles for the given type.
if ( $is_fixed_style ) {
	$allowed_styles = [ 'checkbox' ];
} elseif ( $is_price ) {
	$allowed_styles = [ 'checkbox', 'radio', 'slider' ];
} else {
	$allowed_styles = [ 'checkbox', 'radio', 'dropdown', 'multi_dropdown', 'slider' ];
}

$back_url = Admin::tab_url();
?>

<div class="wc-sf-wrap wc-sf-edit-wrap">

	<p>
		<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">
			&larr; <?php esc_html_e( 'Back to filter list', 'simple-product-filter' ); ?>
		</a>
	</p>

	<h2><?php esc_html_e( 'Filter settings', 'simple-product-filter' ); ?></h2>

	<div id="wc-sf-edit-form" data-filter-type="<?php echo esc_attr( $filter_type ); ?>">
		<input type="hidden" name="id" value="<?php echo esc_attr( $filter['id'] ); ?>" />
		<input type="hidden" name="filter_type" value="<?php echo esc_attr( $filter_type ); ?>" />

		<table class="form-table wc-sf-form-table">

		<!-- Filter name -->
		<tr>
			<th scope="row">
				<label for="wc-sf-label"><?php esc_html_e( 'Filter name', 'simple-product-filter' ); ?></label>
			</th>
				<td>
					<input
						type="text"
						id="wc-sf-label"
						name="label"
						class="regular-text"
						value="<?php echo esc_attr( $filter['label'] ); ?>"
					/>
				</td>
			</tr>

		<!-- Display label -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Display label', 'simple-product-filter' ); ?>
			</th>
			<td>
				<label>
					<input
						type="checkbox"
						name="show_label"
						value="1"
						<?php checked( ! empty( $filter['show_label'] ) ); ?>
					/>
					<?php esc_html_e( 'Display filter name above the filter', 'simple-product-filter' ); ?>
				</label>
			</td>
		</tr>

		<!-- Filter type (info) -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Filter type', 'simple-product-filter' ); ?>
			</th>
				<td>
					<code><?php echo esc_html( $filter_type ); ?></code>
				</td>
			</tr>

		<!-- Display style -->
		<tr>
			<th scope="row">
				<label for="wc-sf-style"><?php esc_html_e( 'Display style', 'simple-product-filter' ); ?></label>
			</th>
			<td>
				<?php if ( $is_fixed_style ) : ?>
					<code><?php echo esc_html( $style_labels[ $filter_style ] ?? $filter_style ); ?></code>
					<p class="description">
						<?php esc_html_e( 'Style is fixed for this filter type.', 'simple-product-filter' ); ?>
					</p>
					<input type="hidden" name="filter_style" value="<?php echo esc_attr( $filter_style ); ?>" />
				<?php else : ?>
					<select id="wc-sf-style" name="filter_style">
						<?php foreach ( $style_labels as $val => $lbl ) : ?>
							<?php if ( ! in_array( $val, $allowed_styles, true ) ) continue; ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_style, $val ); ?>>
								<?php echo esc_html( $lbl ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</td>
		</tr>

		<!-- Hide empty values -->
		<?php if ( ! $is_fixed_style ) : ?>
		<tr class="wc-sf-row-hide-empty">
			<th scope="row">
				<?php esc_html_e( 'Hide empty', 'simple-product-filter' ); ?>
			</th>
			<td>
				<label>
					<input
						type="checkbox"
						name="config[hide_empty]"
						value="1"
						<?php checked( $config['hide_empty'] ?? false ); ?>
					/>
					<?php esc_html_e( 'Do not display values without products', 'simple-product-filter' ); ?>
				</label>
			</td>
		</tr>
		<?php endif; ?>

		</table>

	<!-- Section: Status filter (fixed configuration of stock statuses) -->
	<?php if ( $is_status ) : ?>
	<div class="wc-sf-section wc-sf-section-status">
		<h3><?php esc_html_e( 'Stock statuses', 'simple-product-filter' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Configure the display and description of individual stock statuses.', 'simple-product-filter' ); ?>
		</p>
		<table class="form-table">
			<?php
			// Load all registered statuses including custom ones (via woocommerce_product_stock_status_options).
			$status_options = wc_get_product_stock_status_options();
			foreach ( $status_options as $status_key => $default_label ) :
				$enabled = $config['values'][ $status_key ]['enabled'] ?? false;
				$label   = $config['values'][ $status_key ]['label'] ?? $default_label;
			?>
			<tr>
				<th scope="row">
					<code><?php echo esc_html( $status_key ); ?></code>
				</th>
				<td>
					<label>
						<input
							type="checkbox"
							name="config[values][<?php echo esc_attr( $status_key ); ?>][enabled]"
							value="1"
							<?php checked( $enabled ); ?>
						/>
						<?php esc_html_e( 'Display', 'simple-product-filter' ); ?>
					</label>
					&nbsp;
					<input
						type="text"
						name="config[values][<?php echo esc_attr( $status_key ); ?>][label]"
						value="<?php echo esc_attr( $label ); ?>"
						class="regular-text"
						placeholder="<?php echo esc_attr( $default_label ); ?>"
					/>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>
	</div>
	<?php endif; ?>

	<!-- Section: Slider -->
	<div class="wc-sf-section wc-sf-section-slider" style="<?php echo ( $is_slider ? '' : 'display:none;' ); ?>">
		<h3><?php esc_html_e( 'Slider settings', 'simple-product-filter' ); ?></h3>

		<?php if ( ! empty( $price_range ) ) : ?>
		<p class="description">
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %1$s: minimum price, %2$s: maximum price */
					__( 'Current values in the shop: from %1$s to %2$s', 'simple-product-filter' ),
					wc_price( $price_range['min'] ),
					wc_price( $price_range['max'] )
				)
			);
			?>
		</p>
		<?php endif; ?>

		<?php
		// If min/max are not yet stored in config, use actual shop values as default.
		$slider_min_default = isset( $config['min'] ) ? $config['min'] : ( $price_range['min'] ?? 0 );
		$slider_max_default = isset( $config['max'] ) ? $config['max'] : ( $price_range['max'] ?? 1000 );
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="wc-sf-slider-min"><?php esc_html_e( 'Minimum value', 'simple-product-filter' ); ?></label>
				</th>
				<td>
					<input
						type="number"
						id="wc-sf-slider-min"
						name="config[min]"
						value="<?php echo esc_attr( $slider_min_default ); ?>"
						class="small-text"
						step="any"
					/>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wc-sf-slider-max"><?php esc_html_e( 'Maximum value', 'simple-product-filter' ); ?></label>
				</th>
				<td>
					<input
						type="number"
						id="wc-sf-slider-max"
						name="config[max]"
						value="<?php echo esc_attr( $slider_max_default ); ?>"
						class="small-text"
						step="any"
					/>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wc-sf-slider-step"><?php esc_html_e( 'Step', 'simple-product-filter' ); ?></label>
				</th>
				<td>
					<input
						type="number"
						id="wc-sf-slider-step"
						name="config[step]"
						value="<?php echo esc_attr( $config['step'] ?? '' ); ?>"
						class="small-text"
						min="0"
						step="any"
					/>
					<p class="description">
						<?php esc_html_e( 'Leave empty for free range.', 'simple-product-filter' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<!-- Section: Price ranges (for price with checkbox/radio) -->
	<div class="wc-sf-section wc-sf-section-ranges" style="<?php echo ( $show_ranges ? '' : 'display:none;' ); ?>">
		<h3><?php esc_html_e( 'Price ranges', 'simple-product-filter' ); ?></h3>

		<?php if ( $is_price && ! empty( $price_range ) ) : ?>
		<p class="description">
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %1$s: minimum price, %2$s: maximum price */
					__( 'Product prices in the shop: from %1$s to %2$s', 'simple-product-filter' ),
					wc_price( $price_range['min'] ),
					wc_price( $price_range['max'] )
				)
			);
			?>
		</p>
		<?php endif; ?>

		<p class="description">
			<?php esc_html_e( 'Define price ranges. Leave "To" empty for unlimited upper limit.', 'simple-product-filter' ); ?>
		</p>
		<table class="wc-sf-ranges-table widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'From', 'simple-product-filter' ); ?></th>
					<th><?php esc_html_e( 'To', 'simple-product-filter' ); ?></th>
					<th><?php esc_html_e( 'Description (optional)', 'simple-product-filter' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="wc-sf-ranges-list">
				<?php if ( ! empty( $config['ranges'] ) ) : ?>
					<?php foreach ( $config['ranges'] as $i => $range ) : ?>
					<tr class="wc-sf-range-row">
						<td>
							<input
								type="number"
								name="config[ranges][<?php echo esc_attr( $i ); ?>][min]"
								value="<?php echo esc_attr( $range['min'] ?? '' ); ?>"
								class="small-text"
								step="any"
								min="0"
							/>
						</td>
						<td>
							<input
								type="number"
								name="config[ranges][<?php echo esc_attr( $i ); ?>][max]"
								value="<?php echo esc_attr( $range['max'] ?? '' ); ?>"
								class="small-text"
								step="any"
								min="0"
								placeholder="∞"
							/>
						</td>
						<td>
							<input
								type="text"
								name="config[ranges][<?php echo esc_attr( $i ); ?>][label]"
								value="<?php echo esc_attr( $range['label'] ?? '' ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g., Up to 100 €', 'simple-product-filter' ); ?>"
							/>
						</td>
						<td>
							<button type="button" class="button button-small wc-sf-remove-range">
								<?php esc_html_e( 'Remove', 'simple-product-filter' ); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<p>
			<button type="button" id="wc-sf-add-range" class="button">
				+ <?php esc_html_e( 'Add range', 'simple-product-filter' ); ?>
			</button>
		</p>
	</div>

	<!-- Section: Value selection (for taxonomy/meta with style other than slider/ranges) -->
	<?php if ( ! $is_fixed_style && ! $is_price ) : ?>
	<div class="wc-sf-section wc-sf-section-values" style="<?php echo ( $is_slider ? 'display:none;' : '' ); ?>">
		<h3><?php esc_html_e( 'Value selection', 'simple-product-filter' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Check values to display. If you don\'t check anything, all values will be displayed.', 'simple-product-filter' ); ?>
		</p>

		<p>
			<button type="button" id="wc-sf-select-all-values" class="button"<?php echo empty( $available_values ) ? ' style="display:none;"' : ''; ?>>
				<?php esc_html_e( 'Select all', 'simple-product-filter' ); ?>
			</button>
			<button type="button" id="wc-sf-deselect-all-values" class="button"<?php echo empty( $available_values ) ? ' style="display:none;"' : ''; ?>>
				<?php esc_html_e( 'Deselect all', 'simple-product-filter' ); ?>
			</button>
			<span class="wc-sf-spinner spinner"></span>
		</p>

		<div id="wc-sf-values-list">
			<?php if ( ! empty( $available_values ) ) : ?>
			<div class="wc-sf-values-picker">
				<?php
				$include_values = $config['include_values'] ?? [];
				foreach ( $available_values as $item ) :
					$checked = in_array( $item['value'], $include_values, true );
					$label   = esc_html( $item['label'] );
					if ( isset( $item['count'] ) ) {
						$label .= ' (' . (int) $item['count'] . ')';
					}
				?>
				<label>
					<input
						type="checkbox"
						name="config[include_values][]"
						value="<?php echo esc_attr( $item['value'] ); ?>"
						<?php checked( $checked ); ?>
					/>
					<?php echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- label is escaped above ?>
				</label>
				<?php endforeach; ?>
			</div>
			<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'No values were found for this filter type.', 'simple-product-filter' ); ?>
			</p>
			<?php endif; ?>
		</div>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="wc-sf-sort-by"><?php esc_html_e( 'Sorting', 'simple-product-filter' ); ?></label>
				</th>
				<td>
					<select id="wc-sf-sort-by" name="config[sort_by]">
						<option value="name" <?php selected( $config['sort_by'] ?? 'name', 'name' ); ?>>
							<?php esc_html_e( 'By name A→Z', 'simple-product-filter' ); ?>
						</option>
						<option value="name_desc" <?php selected( $config['sort_by'] ?? '', 'name_desc' ); ?>>
							<?php esc_html_e( 'By name Z→A', 'simple-product-filter' ); ?>
						</option>
						<option value="count" <?php selected( $config['sort_by'] ?? '', 'count' ); ?>>
							<?php esc_html_e( 'By count ↑', 'simple-product-filter' ); ?>
						</option>
						<option value="count_desc" <?php selected( $config['sort_by'] ?? '', 'count_desc' ); ?>>
							<?php esc_html_e( 'By count ↓', 'simple-product-filter' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Filter logic', 'simple-product-filter' ); ?>
				</th>
				<td>
					<label>
						<input type="radio" name="config[logic]" value="or"
							<?php checked( ( $config['logic'] ?? 'or' ) === 'or' ); ?> />
						<?php esc_html_e( 'OR — display products with at least one value', 'simple-product-filter' ); ?>
					</label>
					<br />
					<label>
						<input type="radio" name="config[logic]" value="and"
							<?php checked( ( $config['logic'] ?? 'or' ) === 'and' ); ?> />
						<?php esc_html_e( 'AND — display only products with all values', 'simple-product-filter' ); ?>
					</label>
				</td>
			</tr>
		</table>
	</div>
	<?php endif; ?>

	<!-- Form buttons -->
	<p class="submit">
		<button type="button" id="wc-sf-save-btn" class="button button-primary">
			<?php esc_html_e( 'Save filter', 'simple-product-filter' ); ?>
		</button>
		<span class="wc-sf-spinner spinner"></span>
		<span class="wc-sf-msg"></span>
	</p>

	</div>

</div>
