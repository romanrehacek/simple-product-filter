<?php
/**
 * Template partial: Active filter chip/tag.
 *
 * Variables available in this template:
 * @var string $label      The label displayed on the chip.
 * @var string $value_slug Filter value (slug).
 * @var string $filter_type Filter type.
 *
 * Override: copy to {theme}/simple-product-filter/partials/active-chip.php
 *
 * Note: Fully functional only in Phase 2b (filtering).
 *
 * @package Simple_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<span class="wcsf__active-chip"
	  data-filter-type="<?php echo esc_attr( $filter_type ?? '' ); ?>"
	  data-value="<?php echo esc_attr( $value_slug ?? '' ); ?>">
	<span class="wcsf__active-chip-label"><?php echo esc_html( $label ?? '' ); ?></span>
	<button type="button"
			class="wcsf__active-chip-remove"
			<?php /* translators: %s: active filter label. */ ?>
			aria-label="<?php echo esc_attr( sprintf( __( 'Remove filter: %s', 'simple-product-filter' ), $label ?? '' ) ); ?>">
		<span aria-hidden="true">&times;</span>
	</button>
</span>
