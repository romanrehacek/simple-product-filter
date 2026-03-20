<?php
/**
 * Template: Wrapper for entire filter block.
 *
 * Variables available in this template:
 * @var array<int, array<string, mixed>> $filters     Array of filters from DB.
 * @var string                           $layout      Layout type: 'sidebar' | 'horizontal'.
 * @var bool                             $collapsible Whether filters are collapsible.
 * @var bool                             $collapsed   Whether filters are collapsed by default.
 * @var array<string, mixed>             $atts        Shortcode attributes.
 *
 * Override: copy to {theme}/simple-product-filter/filter-wrapper.php
 *
 * @package Simple_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Simple_Product_Filter\Shortcode;
use Simple_Product_Filter\Template;

$settings    = get_option( 'spf_settings', [] );
$filter_mode = $settings['filter_mode'] ?? 'ajax';
$show_reset  = (bool) apply_filters( 'spf_show_reset_button', $settings['show_reset_button'] ?? true );
$reset_label = (string) apply_filters( 'spf_reset_button_label', $settings['reset_button_text'] ?? __( 'Clear filters', 'simple-product-filter' ) );
?>
<div class="wcsf wcsf--<?php echo esc_attr( $layout ); ?>" data-layout="<?php echo esc_attr( $layout ); ?>" data-filter-mode="<?php echo esc_attr( $filter_mode ); ?>">

	<?php if ( 'horizontal' === $layout ) : ?>
		<?php Template::get_template( 'partials/filter-toggle-bar.php', compact( 'filters', 'layout', 'collapsible', 'collapsed' ) ); ?>
	<?php endif; ?>

	<form class="wcsf__form" method="get" action="<?php echo esc_url( get_pagenum_link( 1 ) ); ?>" novalidate>

	<?php
	// Pagination — reset to page 1 on every form submission.
	// In AJAX and reload modes JS prevents default submit and handles it itself.
	?>
	<input type="hidden" name="paged" value="1">

	<div class="wcsf__filters" role="group" aria-label="<?php esc_attr_e( 'Product filters', 'simple-product-filter' ); ?>">

			<?php foreach ( $filters as $filter ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Shortcode::render_filter_item( $filter, $layout, $collapsible, $collapsed );
				?>
			<?php endforeach; ?>

		</div>

	<?php if ( 'submit' === $filter_mode ) : ?>
		<div class="wcsf__submit-row">
			<button type="submit" class="wcsf__submit-btn">
				<?php esc_html_e( 'Apply filters', 'simple-product-filter' ); ?>
			</button>
		</div>
	<?php endif; ?>

</form>

<?php if ( 'horizontal' === $layout ) : ?>
	<div class="wcsf__active-bar" aria-live="polite" aria-label="<?php esc_attr_e( 'Active filters', 'simple-product-filter' ); ?>">
			<div class="wcsf__active-bar-chips"></div>
			<?php if ( $show_reset ) : ?>
				<button type="button" class="wcsf__reset-btn wcsf__reset-btn--inline">
					<?php echo esc_html( $reset_label ); ?>
				</button>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( 'sidebar' === $layout ) : ?>
		<?php Template::get_template( 'partials/reset-button.php', [] ); ?>
	<?php endif; ?>

</div>

