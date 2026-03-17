<?php
/**
 * Template: Editácia filtra.
 *
 * Dostupné premenné:
 *   $filter  array  Dáta filtra z Filter_Manager::get()
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Simple_Filter\Admin\Admin;

$config       = $filter['config'] ?? [];
$filter_type  = $filter['filter_type'] ?? '';
$filter_style = $filter['filter_style'] ?? 'checkbox';

// Určenie skupiny filtra.
$is_fixed_style  = in_array( $filter_type, [ 'status', 'sale' ], true );
$is_taxonomy     = in_array( $filter_type, [ 'brand' ], true ) || str_starts_with( $filter_type, 'attribute_' );
$is_meta         = str_starts_with( $filter_type, 'meta_' );
$is_price        = 'price' === $filter_type;
$is_status       = 'status' === $filter_type;
$is_slider       = 'slider' === $filter_style;
$has_ranges      = isset( $config['ranges'] ) && is_array( $config['ranges'] );

$style_labels = [
	'checkbox'       => __( 'Checkboxy', 'wc-simple-filter' ),
	'radio'          => __( 'Radio', 'wc-simple-filter' ),
	'dropdown'       => __( 'Dropdown', 'wc-simple-filter' ),
	'multi_dropdown' => __( 'Multi-dropdown', 'wc-simple-filter' ),
	'slider'         => __( 'Posuvník', 'wc-simple-filter' ),
];

// Povolené štýly pre daný typ.
if ( $is_fixed_style ) {
	$allowed_styles = [ 'checkbox' ];
} elseif ( $is_price ) {
	$allowed_styles = [ 'checkbox', 'radio', 'slider' ];
} else {
	$allowed_styles = [ 'checkbox', 'radio', 'dropdown', 'multi_dropdown', 'slider' ];
}

$back_url = Admin::tab_url();
?>

<div class="wc-sf-wrap wc-sf-edit-wrap">

	<p>
		<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">
			&larr; <?php esc_html_e( 'Späť na zoznam filtrov', 'wc-simple-filter' ); ?>
		</a>
	</p>

	<h2><?php esc_html_e( 'Nastavenia filtra', 'wc-simple-filter' ); ?></h2>

	<form id="wc-sf-edit-form" method="post">
		<input type="hidden" name="id" value="<?php echo esc_attr( $filter['id'] ); ?>" />

		<table class="form-table wc-sf-form-table">

			<!-- Názov filtra -->
			<tr>
				<th scope="row">
					<label for="wc-sf-label"><?php esc_html_e( 'Názov filtra', 'wc-simple-filter' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="wc-sf-label"
						name="label"
						class="regular-text"
						value="<?php echo esc_attr( $filter['label'] ); ?>"
					/>
				</td>
			</tr>

			<!-- Zobraziť názov -->
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Zobraziť názov', 'wc-simple-filter' ); ?>
				</th>
				<td>
					<label>
						<input
							type="checkbox"
							name="show_label"
							value="1"
							<?php checked( ! empty( $filter['show_label'] ) ); ?>
						/>
						<?php esc_html_e( 'Zobraziť názov filtra nad filtrom', 'wc-simple-filter' ); ?>
					</label>
				</td>
			</tr>

			<!-- Typ filtra (info) -->
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Typ filtra', 'wc-simple-filter' ); ?>
				</th>
				<td>
					<code><?php echo esc_html( $filter_type ); ?></code>
				</td>
			</tr>

			<!-- Štýl zobrazenia -->
			<tr>
				<th scope="row">
					<label for="wc-sf-style"><?php esc_html_e( 'Štýl zobrazenia', 'wc-simple-filter' ); ?></label>
				</th>
				<td>
					<?php if ( $is_fixed_style ) : ?>
						<code><?php echo esc_html( $style_labels[ $filter_style ] ?? $filter_style ); ?></code>
						<p class="description">
							<?php esc_html_e( 'Štýl je pre tento typ filtra pevne nastavený.', 'wc-simple-filter' ); ?>
						</p>
						<input type="hidden" name="filter_style" value="<?php echo esc_attr( $filter_style ); ?>" />
					<?php else : ?>
						<select id="wc-sf-style" name="filter_style">
							<?php foreach ( $style_labels as $val => $lbl ) : ?>
								<?php if ( ! in_array( $val, $allowed_styles, true ) ) continue; ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_style, $val ); ?>>
									<?php echo esc_html( $lbl ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</td>
			</tr>

			<!-- Skryť prázdne hodnoty -->
			<?php if ( ! $is_fixed_style ) : ?>
			<tr class="wc-sf-row-hide-empty">
				<th scope="row">
					<?php esc_html_e( 'Skryť prázdne', 'wc-simple-filter' ); ?>
				</th>
				<td>
					<label>
						<input
							type="checkbox"
							name="config[hide_empty]"
							value="1"
							<?php checked( $config['hide_empty'] ?? true ); ?>
						/>
						<?php esc_html_e( 'Nezobrazovať hodnoty bez produktov', 'wc-simple-filter' ); ?>
					</label>
				</td>
			</tr>
			<?php endif; ?>

		</table>

		<!-- Sekcia: Status filter (fixná konfigurácia stavov) -->
		<?php if ( $is_status ) : ?>
		<div class="wc-sf-section wc-sf-section-status">
			<h3><?php esc_html_e( 'Stavy skladu', 'wc-simple-filter' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Nastavte zobrazenie a popis jednotlivých stavov.', 'wc-simple-filter' ); ?>
			</p>
			<table class="form-table">
				<?php
				$status_defaults = [
					'instock'     => __( 'Na sklade', 'wc-simple-filter' ),
					'outofstock'  => __( 'Vypredané', 'wc-simple-filter' ),
					'onbackorder' => __( 'Na objednávku', 'wc-simple-filter' ),
				];
				foreach ( $status_defaults as $status_key => $default_label ) :
					$enabled = $config['values'][ $status_key ]['enabled'] ?? true;
					$label   = $config['values'][ $status_key ]['label'] ?? $default_label;
				?>
				<tr>
					<th scope="row">
						<code><?php echo esc_html( $status_key ); ?></code>
					</th>
					<td>
						<label>
							<input
								type="checkbox"
								name="config[values][<?php echo esc_attr( $status_key ); ?>][enabled]"
								value="1"
								<?php checked( $enabled ); ?>
							/>
							<?php esc_html_e( 'Zobraziť', 'wc-simple-filter' ); ?>
						</label>
						&nbsp;
						<input
							type="text"
							name="config[values][<?php echo esc_attr( $status_key ); ?>][label]"
							value="<?php echo esc_attr( $label ); ?>"
							class="regular-text"
							placeholder="<?php echo esc_attr( $default_label ); ?>"
						/>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>
		</div>
		<?php endif; ?>

		<!-- Sekcia: Slider -->
		<div class="wc-sf-section wc-sf-section-slider" style="<?php echo ( $is_slider ? '' : 'display:none;' ); ?>">
			<h3><?php esc_html_e( 'Nastavenia posuvníka', 'wc-simple-filter' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="wc-sf-slider-min"><?php esc_html_e( 'Minimálna hodnota', 'wc-simple-filter' ); ?></label>
					</th>
					<td>
						<input
							type="number"
							id="wc-sf-slider-min"
							name="config[min]"
							value="<?php echo esc_attr( $config['min'] ?? 0 ); ?>"
							class="small-text"
							step="any"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wc-sf-slider-max"><?php esc_html_e( 'Maximálna hodnota', 'wc-simple-filter' ); ?></label>
					</th>
					<td>
						<input
							type="number"
							id="wc-sf-slider-max"
							name="config[max]"
							value="<?php echo esc_attr( $config['max'] ?? 1000 ); ?>"
							class="small-text"
							step="any"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wc-sf-slider-step"><?php esc_html_e( 'Krok', 'wc-simple-filter' ); ?></label>
					</th>
					<td>
						<input
							type="number"
							id="wc-sf-slider-step"
							name="config[step]"
							value="<?php echo esc_attr( $config['step'] ?? '' ); ?>"
							class="small-text"
							min="0"
							step="any"
						/>
						<p class="description">
							<?php esc_html_e( 'Nechajte prázdne pre voľný rozsah.', 'wc-simple-filter' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Sekcia: Rozsahy (radio/checkbox s ranges) -->
		<div class="wc-sf-section wc-sf-section-ranges" style="<?php echo ( $has_ranges && ! $is_slider ? '' : 'display:none;' ); ?>">
			<h3><?php esc_html_e( 'Rozsahy hodnôt', 'wc-simple-filter' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Definujte cenové alebo číselné rozsahy. Nechajte „Do" prázdne pre neobmedzený horný limit.', 'wc-simple-filter' ); ?>
			</p>
			<table class="wc-sf-ranges-table widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Od', 'wc-simple-filter' ); ?></th>
						<th><?php esc_html_e( 'Do', 'wc-simple-filter' ); ?></th>
						<th><?php esc_html_e( 'Popis', 'wc-simple-filter' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="wc-sf-ranges-list">
					<?php if ( ! empty( $config['ranges'] ) ) : ?>
						<?php foreach ( $config['ranges'] as $i => $range ) : ?>
						<tr class="wc-sf-range-row">
							<td>
								<input
									type="number"
									name="config[ranges][<?php echo esc_attr( $i ); ?>][min]"
									value="<?php echo esc_attr( $range['min'] ?? '' ); ?>"
									class="small-text"
									step="any"
								/>
							</td>
							<td>
								<input
									type="number"
									name="config[ranges][<?php echo esc_attr( $i ); ?>][max]"
									value="<?php echo esc_attr( $range['max'] ?? '' ); ?>"
									class="small-text"
									step="any"
									placeholder="∞"
								/>
							</td>
							<td>
								<input
									type="text"
									name="config[ranges][<?php echo esc_attr( $i ); ?>][label]"
									value="<?php echo esc_attr( $range['label'] ?? '' ); ?>"
									class="regular-text"
								/>
							</td>
							<td>
								<button type="button" class="button button-small wc-sf-remove-range">
									<?php esc_html_e( 'Odstrániť', 'wc-simple-filter' ); ?>
								</button>
							</td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<p>
				<button type="button" id="wc-sf-add-range" class="button">
					+ <?php esc_html_e( 'Pridať rozsah', 'wc-simple-filter' ); ?>
				</button>
			</p>
		</div>

		<!-- Sekcia: Výber hodnôt (pre taxonomy/meta so štýlom iným ako slider/ranges) -->
		<?php if ( ! $is_fixed_style && ! $is_price ) : ?>
		<div class="wc-sf-section wc-sf-section-values" style="<?php echo ( $is_slider ? 'display:none;' : '' ); ?>">
			<h3><?php esc_html_e( 'Výber hodnôt', 'wc-simple-filter' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Zaškrtnite hodnoty, ktoré sa majú zobraziť. Ak nič nezaškrtnete, zobrazia sa všetky.', 'wc-simple-filter' ); ?>
			</p>

			<p>
				<button type="button" id="wc-sf-load-values" class="button"
						data-filter-type="<?php echo esc_attr( $filter_type ); ?>">
					<?php esc_html_e( 'Načítať dostupné hodnoty', 'wc-simple-filter' ); ?>
				</button>
				<span class="wc-sf-spinner spinner"></span>
			</p>

			<div id="wc-sf-values-list">
				<?php
				$include_values = $config['include_values'] ?? [];
				if ( ! empty( $include_values ) ) :
				?>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: počet hodnôt */
							__( 'Aktuálne zahrnuté: %d hodnôt. Kliknite „Načítať" pre editáciu.', 'wc-simple-filter' ),
							count( $include_values )
						)
					);
					?>
				</p>
				<?php
				foreach ( $include_values as $val ) :
					?>
					<input type="hidden" name="config[include_values][]" value="<?php echo esc_attr( $val ); ?>" />
					<?php
				endforeach;
				endif;
				?>
			</div>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="wc-sf-sort-by"><?php esc_html_e( 'Zoradenie', 'wc-simple-filter' ); ?></label>
					</th>
					<td>
						<select id="wc-sf-sort-by" name="config[sort_by]">
							<option value="name" <?php selected( $config['sort_by'] ?? 'name', 'name' ); ?>>
								<?php esc_html_e( 'Podľa názvu A→Z', 'wc-simple-filter' ); ?>
							</option>
							<option value="name_desc" <?php selected( $config['sort_by'] ?? '', 'name_desc' ); ?>>
								<?php esc_html_e( 'Podľa názvu Z→A', 'wc-simple-filter' ); ?>
							</option>
							<option value="count" <?php selected( $config['sort_by'] ?? '', 'count' ); ?>>
								<?php esc_html_e( 'Podľa počtu ↑', 'wc-simple-filter' ); ?>
							</option>
							<option value="count_desc" <?php selected( $config['sort_by'] ?? '', 'count_desc' ); ?>>
								<?php esc_html_e( 'Podľa počtu ↓', 'wc-simple-filter' ); ?>
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Logika filtrovania', 'wc-simple-filter' ); ?>
					</th>
					<td>
						<label>
							<input type="radio" name="config[logic]" value="or"
								<?php checked( ( $config['logic'] ?? 'or' ) === 'or' ); ?> />
							<?php esc_html_e( 'OR — zobraziť produkty s aspoň jednou hodnotou', 'wc-simple-filter' ); ?>
						</label>
						<br />
						<label>
							<input type="radio" name="config[logic]" value="and"
								<?php checked( ( $config['logic'] ?? 'or' ) === 'and' ); ?> />
							<?php esc_html_e( 'AND — zobraziť iba produkty so všetkými hodnotami', 'wc-simple-filter' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>
		<?php endif; ?>

		<!-- Tlačidlá formulára -->
		<p class="submit">
			<button type="submit" id="wc-sf-save-btn" class="button button-primary">
				<?php esc_html_e( 'Uložiť filter', 'wc-simple-filter' ); ?>
			</button>
			<span class="wc-sf-spinner spinner"></span>
			<span class="wc-sf-msg"></span>
		</p>

	</form>

</div>
