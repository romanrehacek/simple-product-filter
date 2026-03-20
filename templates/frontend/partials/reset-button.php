<?php
/**
 * Template partial: Reset button.
 *
 * Override: copy to {theme}/simple-product-filter/partials/reset-button.php
 *
 * @package Simple_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings         = get_option( 'spf_settings', [] );
$show_reset       = $settings['show_reset_button'] ?? true;
$reset_label      = $settings['reset_button_text'] ?? __( 'Clear filters', 'simple-product-filter' );

/**
 * Filter to show reset button.
 *
 * @param bool $show_reset Show reset button?
 */
$show_reset = (bool) apply_filters( 'spf_show_reset_button', $show_reset );

if ( ! $show_reset ) {
	return;
}

/**
 * Filter for reset button label.
 *
 * @param string $reset_label Button text.
 */
$reset_label = (string) apply_filters( 'spf_reset_button_label', $reset_label );
?>
<div class="wcsf__reset-wrapper">
	<button type="button" class="wcsf__reset-btn">
		<?php echo esc_html( $reset_label ); ?>
	</button>
</div>
