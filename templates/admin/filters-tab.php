<?php
/**
 * Template: "Filters" tab — filter list with repeater.
 *
 * Available variables:
 *   $filters  array  List of filters from Filter_Manager::get_all()
 *
 * @package Simple_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Simple_Product_Filter\Admin\Admin;

$style_labels = [
	'checkbox'      => __( 'Checkboxes', 'simple-product-filter' ),
	'radio'         => __( 'Radio buttons', 'simple-product-filter' ),
	'dropdown'      => __( 'Dropdown', 'simple-product-filter' ),
	'multi_dropdown'=> __( 'Multi-dropdown', 'simple-product-filter' ),
	'slider'        => __( 'Slider', 'simple-product-filter' ),
];

$fixed_style_types = [ 'status', 'sale' ];
?>

<div class="wc-sf-wrap">

	<table class="widefat wc-sf-filters-table" id="wc-sf-filters-list">
		<thead>
			<tr>
				<th class="wc-sf-col-handle"></th>
				<th><?php esc_html_e( 'Name', 'simple-product-filter' ); ?></th>
				<th><?php esc_html_e( 'Type', 'simple-product-filter' ); ?></th>
				<th><?php esc_html_e( 'Style', 'simple-product-filter' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'simple-product-filter' ); ?></th>
			</tr>
		</thead>
		<tbody id="wc-sf-sortable">

			<?php if ( empty( $filters ) ) : ?>
				<tr class="wc-sf-no-filters">
					<td colspan="5">
						<?php esc_html_e( 'No filters. Add the first filter below.', 'simple-product-filter' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $filters as $filter ) :
					$is_fixed = in_array( $filter['filter_type'], $fixed_style_types, true );
					$type_label = $filter['filter_type'];
					?>
					<tr class="wc-sf-filter-row" data-id="<?php echo esc_attr( $filter['id'] ); ?>">
						<td class="wc-sf-col-handle">
							<span class="wc-sf-drag-handle dashicons dashicons-move"></span>
						</td>
						<td>
							<?php echo esc_html( $filter['label'] ?: __( '(untitled)', 'simple-product-filter' ) ); ?>
						</td>
						<td>
							<?php echo esc_html( $type_label ); ?>
						</td>
						<td>
							<select
								class="wc-sf-style-select"
								data-filter-id="<?php echo esc_attr( $filter['id'] ); ?>"
								<?php disabled( $is_fixed, true ); ?>
							>
								<?php foreach ( $style_labels as $val => $lbl ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter['filter_style'], $val ); ?>>
										<?php echo esc_html( $lbl ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td class="wc-sf-actions">
							<a href="<?php echo esc_url( Admin::filter_edit_url( $filter['id'] ) ); ?>"
							   class="button button-small"
							   title="<?php esc_attr_e( 'Filter settings', 'simple-product-filter' ); ?>">
								<span class="dashicons dashicons-admin-settings"></span>
							</a>
							<button type="button"
									class="button button-small wc-sf-delete-filter"
									data-id="<?php echo esc_attr( $filter['id'] ); ?>"
									title="<?php esc_attr_e( 'Delete filter', 'simple-product-filter' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>

		</tbody>
	</table>

	<div class="wc-sf-add-filter-wrap">
		<h3><?php esc_html_e( 'Add new filter', 'simple-product-filter' ); ?></h3>
		<table class="form-table wc-sf-add-form">
			<tr>
				<th scope="row">
					<label for="wc-sf-new-type"><?php esc_html_e( 'Filter type', 'simple-product-filter' ); ?></label>
				</th>
				<td>
					<select id="wc-sf-new-type" name="spf_new_type">
						<option value=""><?php esc_html_e( '— Select type —', 'simple-product-filter' ); ?></option>
						<optgroup label="<?php esc_attr_e( 'Basic', 'simple-product-filter' ); ?>">
							<option value="brand"><?php esc_html_e( 'Brand', 'simple-product-filter' ); ?></option>
							<option value="status"><?php esc_html_e( 'Stock status', 'simple-product-filter' ); ?></option>
							<option value="sale"><?php esc_html_e( 'Sale', 'simple-product-filter' ); ?></option>
							<option value="price"><?php esc_html_e( 'Price', 'simple-product-filter' ); ?></option>
						</optgroup>
						<?php
						$attributes = wc_get_attribute_taxonomies();
						if ( ! empty( $attributes ) ) :
						?>
						<optgroup label="<?php esc_attr_e( 'Product attributes', 'simple-product-filter' ); ?>">
							<?php foreach ( $attributes as $attr ) : ?>
								<option value="attribute_pa_<?php echo esc_attr( $attr->attribute_name ); ?>">
									<?php echo esc_html( $attr->attribute_label ); ?>
								</option>
							<?php endforeach; ?>
						</optgroup>
						<?php endif; ?>
						<optgroup label="<?php esc_attr_e( 'Custom field', 'simple-product-filter' ); ?>">
							<option value="meta_custom"><?php esc_html_e( 'Custom field (enter key)', 'simple-product-filter' ); ?></option>
						</optgroup>
					</select>
				</td>
			</tr>
			<tr id="wc-sf-meta-key-row" style="display:none;">
				<th scope="row">
					<label for="wc-sf-new-meta-key"><?php esc_html_e( 'Meta key', 'simple-product-filter' ); ?></label>
				</th>
				<td>
					<input type="text"
						   id="wc-sf-new-meta-key"
						   name="spf_new_meta_key"
						   class="regular-text"
						   placeholder="e.g., _my_custom_field"
					/>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wc-sf-new-style"><?php esc_html_e( 'Filter style', 'simple-product-filter' ); ?></label>
				</th>
				<td>
					<select id="wc-sf-new-style" name="spf_new_style">
						<?php foreach ( $style_labels as $val => $lbl ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>">
								<?php echo esc_html( $lbl ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th></th>
				<td>
					<button type="button" id="wc-sf-add-filter-btn" class="button button-primary">
						<?php esc_html_e( '+ Add filter', 'simple-product-filter' ); ?>
					</button>
					<span class="wc-sf-spinner spinner"></span>
					<span class="wc-sf-msg"></span>
				</td>
			</tr>
		</table>
	</div>

</div>
