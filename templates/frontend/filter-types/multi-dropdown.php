<?php
/**
 * Template: Multi-dropdown filter type (multiple select).
 *
 * Variables available in this template:
 * @var array<string, mixed>             $filter  Filter data from DB.
 * @var array<int, array<string, mixed>> $values  Filter values.
 * @var string                           $layout  Layout type.
 *
 * Override: copy to {theme}/simple-product-filter/filter-types/multi-dropdown.php
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
<div class="wcsf__dropdown-wrapper wcsf__dropdown-wrapper--multi">
	<select
		class="wcsf__dropdown wcsf__dropdown--multi"
		name="wcsf[<?php echo esc_attr( $filter_type ); ?>][]"
		aria-label="<?php echo esc_attr( $label ); ?>"
		multiple>
		<?php foreach ( $values as $value ) : ?>
			<option value="<?php echo esc_attr( $value['slug'] ?? '' ); ?>">
				<?php echo esc_html( $value['label'] ?? '' ); ?>
				<?php if ( isset( $value['count'] ) ) : ?>
					(<?php echo absint( $value['count'] ); ?>)
				<?php endif; ?>
			</option>
		<?php endforeach; ?>
	</select>
	<p class="wcsf__dropdown-hint"><?php esc_html_e( 'Ctrl/Cmd + click for multiple values', 'simple-product-filter' ); ?></p>
</div>
