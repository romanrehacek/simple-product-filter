<?php
/**
 * Frontend trieda — enqueue CSS/JS a registrácia frontend hookov.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trieda Frontend.
 *
 * Zodpovedá za:
 * - enqueue frontend CSS a JS
 * - lokalizáciu JS dát
 * - registráciu frontend hookov
 */
class Frontend {

	/**
	 * Inicializuje frontend hooky.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue frontend CSS a JS.
	 * Načíta sa iba na frontend stránkach (nie v admin).
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Enqueue iba ak je shortcode na stránke alebo na shop/archive stránke.
		if ( ! $this->should_enqueue() ) {
			return;
		}

		// Frontend CSS.
		wp_enqueue_style(
			'wc-sf-frontend',
			WC_SF_PLUGIN_URL . 'assets/css/frontend.css',
			[],
			WC_SF_VERSION
		);

		// jQuery UI Slider (vstavaný vo WP — nepotrebuje externe CDN).
		wp_enqueue_script( 'jquery-ui-slider' );

		// Frontend JS.
		wp_enqueue_script(
			'wc-sf-frontend',
			WC_SF_PLUGIN_URL . 'assets/js/frontend.js',
			[ 'jquery', 'jquery-ui-slider' ],
			WC_SF_VERSION,
			true
		);

		// Lokalizácia JS dát.
		wp_localize_script(
			'wc-sf-frontend',
			'WC_SF_Frontend',
			$this->get_js_data()
		);
	}

	/**
	 * Vráti pole dát pre lokalizáciu JS.
	 *
	 * @return array<string, mixed>
	 */
	private function get_js_data(): array {
		$settings = get_option( 'wc_sf_settings', [] );

		return [
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'wc_sf_frontend_nonce' ),
			'filterMode'     => $settings['filter_mode'] ?? 'ajax',
			'i18n'           => [
				'viewMore'     => __( 'Zobraziť viac', 'wc-simple-filter' ),
				'viewLess'     => __( 'Zobraziť menej', 'wc-simple-filter' ),
				'resetAll'     => $settings['reset_button_text'] ?? __( 'Zrušiť filtre', 'wc-simple-filter' ),
				'closeLabel'   => __( 'Zavrieť', 'wc-simple-filter' ),
				'removeFilter' => __( 'Odstrániť filter', 'wc-simple-filter' ),
			],
		];
	}

	/**
	 * Skontroluje, či sa majú načítať assets na aktuálnej stránke.
	 * Vždy načíta — shortcode sa môže objaviť kdekoľvek.
	 * Výkon sa rieši cez podmienené načítavanie v budúcnosti.
	 *
	 * @return bool
	 */
	private function should_enqueue(): bool {
		/**
		 * Filter na kontrolu, či načítať frontend assets.
		 *
		 * @param bool $should Načítať assets? Default true.
		 */
		return (bool) apply_filters( 'wc_sf_enqueue_frontend_assets', true );
	}
}
