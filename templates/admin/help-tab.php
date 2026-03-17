<?php
/**
 * Template: Záložka „Nápoveda".
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wc-sf-wrap wc-sf-help-wrap">

	<h2><?php esc_html_e( 'Ako používať WC Simple Filter', 'wc-simple-filter' ); ?></h2>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Vloženie pomocou shortcodu', 'wc-simple-filter' ); ?></h3>
		<p>
			<?php esc_html_e( 'Vložte shortcode na ľubovoľnú stránku alebo do widgetu:', 'wc-simple-filter' ); ?>
		</p>
		<pre class="wc-sf-code"><code>[wc_simple_filter]</code></pre>
	</div>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Vloženie priamo do PHP šablóny', 'wc-simple-filter' ); ?></h3>
		<p>
			<?php esc_html_e( 'Ak preferujete vloženie priamo do PHP súboru témy:', 'wc-simple-filter' ); ?>
		</p>
		<pre class="wc-sf-code"><code>&lt;?php wc_simple_filter(); ?&gt;</code></pre>
	</div>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Typy filtrov', 'wc-simple-filter' ); ?></h3>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Typ', 'wc-simple-filter' ); ?></th>
					<th><?php esc_html_e( 'Popis', 'wc-simple-filter' ); ?></th>
					<th><?php esc_html_e( 'Dostupné štýly', 'wc-simple-filter' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>brand</code></td>
					<td><?php esc_html_e( 'Filtrovanie podľa značky (taxonomy product_brand)', 'wc-simple-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox, radio, dropdown, multi-dropdown', 'wc-simple-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>status</code></td>
					<td><?php esc_html_e( 'Stav skladu (na sklade, vypredané, na objednávku)', 'wc-simple-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox (pevne nastavený)', 'wc-simple-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>sale</code></td>
					<td><?php esc_html_e( 'Zľavové produkty (v akcii / bez zľavy)', 'wc-simple-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox (pevne nastavený)', 'wc-simple-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>price</code></td>
					<td><?php esc_html_e( 'Cenový filter — rozsahy alebo posuvník', 'wc-simple-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox, radio, slider', 'wc-simple-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>attribute_{slug}</code></td>
					<td><?php esc_html_e( 'WooCommerce atribút produktu (napr. attribute_pa_farba)', 'wc-simple-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox, radio, dropdown, multi-dropdown, slider', 'wc-simple-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>meta_{key}</code></td>
					<td><?php esc_html_e( 'Custom meta pole (zadáva admin — napr. meta_weight)', 'wc-simple-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox, radio, dropdown, multi-dropdown, slider', 'wc-simple-filter' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Index hodnôt (hide-empty)', 'wc-simple-filter' ); ?></h3>
		<p>
			<?php esc_html_e( 'Plugin udržiava interný index počtov produktov pre každú hodnotu filtra. Tento index sa automaticky aktualizuje pri uložení produktu. Ak vidíte neaktuálne hodnoty, môžete ho manuálne prepočítať na záložke Nastavenia.', 'wc-simple-filter' ); ?>
		</p>
	</div>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Plánované funkcie (Fáza 2)', 'wc-simple-filter' ); ?></h3>
		<ul class="ul-disc">
			<li><?php esc_html_e( 'AJAX filtrovanie produktov bez reload stránky', 'wc-simple-filter' ); ?></li>
			<li><?php esc_html_e( 'URL state — zdieľanie a back/forward navigácia', 'wc-simple-filter' ); ?></li>
			<li><?php esc_html_e( 'Frontend CSS štýlovanie', 'wc-simple-filter' ); ?></li>
			<li><?php esc_html_e( 'Integrácia s WooCommerce query', 'wc-simple-filter' ); ?></li>
		</ul>
	</div>

</div>
