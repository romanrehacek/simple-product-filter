<?php
/**
 * Template: Slider filter typ (range slider).
 *
 * Premenné dostupné v tejto šablóne:
 * @var array<string, mixed>             $filter  Dáta filtra z DB.
 * @var array<int, array<string, mixed>> $values  Hodnoty filtra (1 item so slider konfiguráciou).
 * @var string                           $layout  Layout typ.
 *
 * Override: skopíruj do {tema}/wc-simple-filter/filter-types/slider.php
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$filter_id   = (int) ( $filter['id'] ?? 0 );
$filter_type = $filter['filter_type'] ?? '';
$config      = $filter['config'] ?? [];

// Slider konfigurácia z prvej values položky.
$slider_data = $values[0] ?? [];
$min         = isset( $slider_data['min'] ) ? (float) $slider_data['min'] : 0;
$max         = isset( $slider_data['max'] ) ? (float) $slider_data['max'] : 1000;
$step        = isset( $slider_data['step'] ) ? (float) $slider_data['step'] : 1;
?>
<div class="wcsf__slider-wrapper"
	 data-min="<?php echo esc_attr( $min ); ?>"
	 data-max="<?php echo esc_attr( $max ); ?>"
	 data-step="<?php echo esc_attr( $step ); ?>"
	 data-filter-type="<?php echo esc_attr( $filter_type ); ?>">

	<div class="wcsf__slider-values" aria-live="polite">
		<span class="wcsf__slider-value wcsf__slider-value--min" id="wcsf-slider-min-<?php echo esc_attr( $filter_id ); ?>">
			<?php echo esc_html( $min ); ?>
		</span>
		<span class="wcsf__slider-separator" aria-hidden="true"> – </span>
		<span class="wcsf__slider-value wcsf__slider-value--max" id="wcsf-slider-max-<?php echo esc_attr( $filter_id ); ?>">
			<?php echo esc_html( $max ); ?>
		</span>
	</div>

	<div class="wcsf__slider"
		 id="wcsf-slider-<?php echo esc_attr( $filter_id ); ?>"
		 role="group"
		 aria-labelledby="wcsf-filter-body-<?php echo esc_attr( $filter_id ); ?>">
	</div>

	<div class="wcsf__slider-inputs" aria-hidden="true">
		<input type="hidden"
			   name="wcsf[<?php echo esc_attr( $filter_type ); ?>][min]"
			   class="wcsf__slider-input wcsf__slider-input--min"
			   value="">
		<input type="hidden"
			   name="wcsf[<?php echo esc_attr( $filter_type ); ?>][max]"
			   class="wcsf__slider-input wcsf__slider-input--max"
			   value="">
	</div>

</div>
