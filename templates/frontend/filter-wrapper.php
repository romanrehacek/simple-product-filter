<?php
/**
 * Template: Obal celého bloku filtrov.
 *
 * Premenné dostupné v tejto šablóne:
 * @var array<int, array<string, mixed>> $filters     Pole filtrov z DB.
 * @var string                           $layout      Layout typ: 'sidebar' | 'horizontal'.
 * @var bool                             $collapsible Či sú filtre zbaliteľné.
 * @var bool                             $collapsed   Či sú filtre defaultne zbalené.
 * @var array<string, mixed>             $atts        Shortcode atribúty.
 *
 * Override: skopíruj do {tema}/wc-simple-filter/filter-wrapper.php
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Simple_Filter\Shortcode;
use WC_Simple_Filter\Template;

$settings    = get_option( 'wc_sf_settings', [] );
$filter_mode = $settings['filter_mode'] ?? 'ajax';
$show_reset  = (bool) apply_filters( 'wc_sf_show_reset_button', $settings['show_reset_button'] ?? true );
$reset_label = (string) apply_filters( 'wc_sf_reset_button_label', $settings['reset_button_text'] ?? __( 'Zrušiť filtre', 'wc-simple-filter' ) );
?>
<div class="wcsf wcsf--<?php echo esc_attr( $layout ); ?>" data-layout="<?php echo esc_attr( $layout ); ?>" data-filter-mode="<?php echo esc_attr( $filter_mode ); ?>">

	<?php if ( 'horizontal' === $layout ) : ?>
		<?php Template::get_template( 'partials/filter-toggle-bar.php', compact( 'filters', 'layout', 'collapsible', 'collapsed' ) ); ?>
	<?php endif; ?>

	<form class="wcsf__form" method="get" action="<?php echo esc_url( get_pagenum_link( 1 ) ); ?>" novalidate>

		<?php
		// Stránkovanie — reset na 1 pri každom odoslaní formulára.
		// V AJAX a reload režime JS zabraňuje default submit a rieši to sám.
		?>
		<input type="hidden" name="paged" value="1">

		<div class="wcsf__filters" role="group" aria-label="<?php esc_attr_e( 'Filtre produktov', 'wc-simple-filter' ); ?>">

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
					<?php esc_html_e( 'Použiť filtre', 'wc-simple-filter' ); ?>
				</button>
			</div>
		<?php endif; ?>

	</form>

	<?php if ( 'horizontal' === $layout ) : ?>
		<div class="wcsf__active-bar" aria-live="polite" aria-label="<?php esc_attr_e( 'Aktívne filtre', 'wc-simple-filter' ); ?>">
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

