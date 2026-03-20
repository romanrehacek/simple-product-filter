<?php
/**
 * Template: Dropdown filter type (single select).
 *
 * Variables available in this template:
 * @var array<string, mixed>             $filter  Filter data from DB.
 * @var array<int, array<string, mixed>> $values  Filter values.
 * @var string                           $layout  Layout type.
 *
 * Override: copy to {theme}/simple-product-filter/filter-types/dropdown.php
 *
 * @package Simple_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$filter_id   = (int) ( $filter['id'] ?? 0 );
$filter_type = $filter['filter_type'] ?? '';
$label       = $filter['label'] ?? '';
?>
<div class="wcsf__dropdown-wrapper">
	<select
		class="wcsf__dropdown"
		name="wcsf[<?php echo esc_attr( $filter_type ); ?>]"
		aria-label="<?php echo esc_attr( $label ); ?>">
		<option value=""><?php esc_html_e( '— Select —', 'simple-product-filter' ); ?></option>
		<?php foreach ( $values as $value ) : ?>
			<option value="<?php echo esc_attr( $value['slug'] ?? '' ); ?>">
				<?php echo esc_html( $value['label'] ?? '' ); ?>
				<?php if ( isset( $value['count'] ) ) : ?>
					(<?php echo absint( $value['count'] ); ?>)
				<?php endif; ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>
