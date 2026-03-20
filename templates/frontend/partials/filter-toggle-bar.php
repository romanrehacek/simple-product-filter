<?php
/**
 * Template partial: Horizontal layout — toggle bar with pill buttons.
 *
 * Displays filters as "pill" dropdown buttons (horizontal layout).
 *
 * Variables available in this template:
 * @var array<int, array<string, mixed>> $filters     Array of filters from DB.
 * @var string                           $layout      Layout type.
 * @var bool                             $collapsible Whether filters are collapsible.
 * @var bool                             $collapsed   Whether filters are collapsed by default.
 *
 * Override: copy to {theme}/wc-simple-filter/partials/filter-toggle-bar.php
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wcsf__toggle-bar" role="group" aria-label="<?php esc_attr_e( 'Product filters', 'wc-simple-filter' ); ?>">
	<?php foreach ( $filters as $filter ) : ?>
		<?php
		$filter_id    = (int) ( $filter['id'] ?? 0 );
		$label        = esc_html( $filter['label'] ?? '' );
		$filter_type  = esc_attr( $filter['filter_type'] ?? '' );
		$target_id    = 'wcsf-filter-body-' . $filter_id;
		?>
		<button type="button"
				class="wcsf__toggle-pill"
				aria-expanded="false"
				aria-controls="<?php echo esc_attr( $target_id ); ?>"
				data-filter-id="<?php echo esc_attr( $filter_id ); ?>"
				data-filter-type="<?php echo esc_attr( $filter_type ); ?>">
			<span class="wcsf__toggle-pill-label"><?php echo esc_html( $label ); ?></span>
			<span class="wcsf__toggle-pill-icon" aria-hidden="true"></span>
		</button>
	<?php endforeach; ?>
</div>
