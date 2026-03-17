<?php
/**
 * Template: Záložka „Nastavenia".
 *
 * Dostupné premenné:
 *   $settings    array        Nastavenia z wp_options (wc_sf_settings)
 *   $index_time  string|null  Čas posledného prebudovania indexu
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wc-sf-wrap">

	<form id="wc-sf-settings-form" method="post">

		<table class="form-table wc-sf-form-table">

			<!-- Spôsob filtrovania -->
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Spôsob filtrovania', 'wc-simple-filter' ); ?>
				</th>
				<td>
					<label>
						<input type="radio" name="settings[filter_mode]" value="ajax"
							<?php checked( $settings['filter_mode'] ?? 'ajax', 'ajax' ); ?> />
						<?php esc_html_e( 'AJAX — okamžite bez načítania stránky', 'wc-simple-filter' ); ?>
					</label>
					<br />
					<label>
						<input type="radio" name="settings[filter_mode]" value="submit"
							<?php checked( $settings['filter_mode'] ?? 'ajax', 'submit' ); ?> />
						<?php esc_html_e( 'Tlačidlo — odoslanie formulára', 'wc-simple-filter' ); ?>
					</label>
					<br />
					<label>
						<input type="radio" name="settings[filter_mode]" value="reload"
							<?php checked( $settings['filter_mode'] ?? 'ajax', 'reload' ); ?> />
						<?php esc_html_e( 'Reload — refresh stránky pri každej zmene', 'wc-simple-filter' ); ?>
					</label>
				</td>
			</tr>

			<!-- Text tlačidla Filter -->
			<tr>
				<th scope="row">
					<label for="wc-sf-filter-btn-text">
						<?php esc_html_e( 'Text tlačidla „Filtrovať"', 'wc-simple-filter' ); ?>
					</label>
				</th>
				<td>
					<input
						type="text"
						id="wc-sf-filter-btn-text"
						name="settings[filter_button_text]"
						value="<?php echo esc_attr( $settings['filter_button_text'] ?? __( 'Filtrovať', 'wc-simple-filter' ) ); ?>"
						class="regular-text"
					/>
					<p class="description">
						<?php esc_html_e( 'Zobrazí sa pri režime „Tlačidlo".', 'wc-simple-filter' ); ?>
					</p>
				</td>
			</tr>

			<!-- Zobraziť tlačidlo Reset -->
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Tlačidlo Reset', 'wc-simple-filter' ); ?>
				</th>
				<td>
					<label>
						<input
							type="checkbox"
							name="settings[show_reset_button]"
							value="1"
							<?php checked( ! empty( $settings['show_reset_button'] ) ); ?>
						/>
						<?php esc_html_e( 'Zobraziť tlačidlo „Zrušiť filtre"', 'wc-simple-filter' ); ?>
					</label>
				</td>
			</tr>

			<!-- Text tlačidla Reset -->
			<tr>
				<th scope="row">
					<label for="wc-sf-reset-btn-text">
						<?php esc_html_e( 'Text tlačidla Reset', 'wc-simple-filter' ); ?>
					</label>
				</th>
				<td>
					<input
						type="text"
						id="wc-sf-reset-btn-text"
						name="settings[reset_button_text]"
						value="<?php echo esc_attr( $settings['reset_button_text'] ?? __( 'Zrušiť filtre', 'wc-simple-filter' ) ); ?>"
						class="regular-text"
					/>
				</td>
			</tr>

			<!-- Skryť prázdne hodnoty globálne -->
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Skryť prázdne hodnoty', 'wc-simple-filter' ); ?>
				</th>
				<td>
					<label>
						<input
							type="checkbox"
							name="settings[hide_empty]"
							value="1"
							<?php checked( ! empty( $settings['hide_empty'] ) ); ?>
						/>
						<?php esc_html_e( 'Nezobrazovať hodnoty filtrov bez produktov (globálna predvolená hodnota)', 'wc-simple-filter' ); ?>
					</label>
				</td>
			</tr>

			<!-- Zmazať dáta pri odinštalácii -->
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Odinštalácia', 'wc-simple-filter' ); ?>
				</th>
				<td>
					<label>
						<input
							type="checkbox"
							name="settings[delete_on_uninstall]"
							value="1"
							<?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?>
						/>
						<?php esc_html_e( 'Zmazať všetky dáta pluginu pri odinštalácii', 'wc-simple-filter' ); ?>
					</label>
					<p class="description wc-sf-danger-text">
						<?php esc_html_e( 'Pozor: táto akcia je nevratná. Zmažú sa DB tabuľky, nastavenia a cache.', 'wc-simple-filter' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<p class="submit">
			<button type="submit" id="wc-sf-save-settings-btn" class="button button-primary">
				<?php esc_html_e( 'Uložiť nastavenia', 'wc-simple-filter' ); ?>
			</button>
			<span class="wc-sf-spinner spinner"></span>
			<span class="wc-sf-msg"></span>
		</p>

	</form>

	<hr />

	<!-- Sekcia Index -->
	<div class="wc-sf-index-section">
		<h3><?php esc_html_e( 'Index hodnôt filtrov', 'wc-simple-filter' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Index uchováva počty produktov pre každú hodnotu filtra. Slúži na skrytie prázdnych hodnôt (hide-empty).', 'wc-simple-filter' ); ?>
		</p>
		<p>
			<?php if ( $index_time ) : ?>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: čas poslednej aktualizácie */
						__( 'Naposledy prepočítané: %s', 'wc-simple-filter' ),
						$index_time
					)
				);
				?>
			<?php else : ?>
				<?php esc_html_e( 'Index ešte nebol prepočítaný.', 'wc-simple-filter' ); ?>
			<?php endif; ?>
		</p>
		<p>
			<button type="button" id="wc-sf-reindex-btn" class="button button-secondary">
				<?php esc_html_e( 'Prepočítať indexy', 'wc-simple-filter' ); ?>
			</button>
			<span class="wc-sf-spinner spinner"></span>
			<span class="wc-sf-msg" id="wc-sf-reindex-msg"></span>
		</p>
	</div>

</div>
