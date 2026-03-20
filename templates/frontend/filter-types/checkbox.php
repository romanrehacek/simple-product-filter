<?php
/**
 * Template: Checkbox filter type.
 *
 * Variables available in this template:
 * @var array<string, mixed>             $filter  Filter data from DB.
 * @var array<int, array<string, mixed>> $values  Filter values.
 * @var string                           $layout  Layout type.
 *
 * Override: copy to {theme}/simple-product-filter/filter-types/checkbox.php
 *
 * @package Simple_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$filter_id      = (int) ( $filter['id'] ?? 0 );
$filter_type    = $filter['filter_type'] ?? '';
$visible_count  = apply_filters( 'spf_values_visible_count', 5, $filter );
$total          = count( $values );
$has_more       = $total > $visible_count;
?>
<ul class="wcsf__checkbox-list" role="group">
	<?php foreach ( $values as $index => $value ) : ?>
		<?php
		$is_hidden  = $has_more && $index >= $visible_count;
		$value_slug = esc_attr( $value['slug'] ?? '' );
		$input_id   = 'wcsf-' . $filter_id . '-' . $value_slug;
		$item_class = 'wcsf__checkbox-item' . ( $is_hidden ? ' wcsf__checkbox-item--hidden' : '' );
		?>
		<li class="<?php echo esc_attr( $item_class ); ?>">
			<label class="wcsf__checkbox-label" for="<?php echo esc_attr( $input_id ); ?>">
				<input
					type="checkbox"
					class="wcsf__checkbox-input"
					id="<?php echo esc_attr( $input_id ); ?>"
					name="wcsf[<?php echo esc_attr( $filter_type ); ?>][]"
					value="<?php echo esc_attr( $value['slug'] ?? '' ); ?>"
				>
				<span class="wcsf__checkbox-custom" aria-hidden="true"></span>
				<span class="wcsf__checkbox-text"><?php echo esc_html( $value['label'] ?? '' ); ?></span>
				<?php if ( isset( $value['count'] ) ) : ?>
					<span class="wcsf__checkbox-count">(<?php echo absint( $value['count'] ); ?>)</span>
				<?php endif; ?>
			</label>
		</li>
	<?php endforeach; ?>
</ul>

<?php if ( $has_more ) : ?>
	<button type="button"
			class="wcsf__view-more-link"
			data-visible="<?php echo absint( $visible_count ); ?>"
			data-total="<?php echo absint( $total ); ?>"
			aria-expanded="false">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: number of hidden values */
				__( 'Show more (%d)', 'simple-product-filter' ),
				$total - $visible_count
			)
		);
		?>
	</button>
<?php endif; ?>
