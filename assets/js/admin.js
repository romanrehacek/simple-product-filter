/**
 * WC Simple Filter — Admin JavaScript
 *
 * Pokrýva:
 * 1. Repeater filtrov — pridávanie nového filtra (AJAX save)
 * 2. Mazanie filtra z tabuľky (AJAX delete)
 * 3. Drag & drop zoradenie (jQuery UI Sortable → AJAX reorder)
 * 4. Dynamický style-select — aktualizácia dostupných štýlov pri zmene type
 * 5. Záložka Nastavenia — uloženie (AJAX save_settings) + reindex
 * 6. Filter edit stránka — show/hide sekcií podľa zvoleného štýlu
 * 7. Ranges repeater — pridávanie/mazanie riadkov
 * 8. Values picker — načítanie dostupných hodnôt cez AJAX
 */

/* global wcSfAdmin, jQuery */

( function ( $ ) {
	'use strict';

	var ajax    = wcSfAdmin.ajaxUrl;
	var nonce   = wcSfAdmin.nonce;
	var i18n    = wcSfAdmin.i18n;
	var types   = wcSfAdmin.filterTypes || {};

	/* =========================================================
	   Pomocné funkcie
	   ========================================================= */

	/**
	 * Zobrazí správu v .wc-sf-msg elemente.
	 *
	 * @param {jQuery} $msg    Element správy.
	 * @param {string} text    Text správy.
	 * @param {string} type    'success' | 'error' | ''
	 */
	function showMsg( $msg, text, type ) {
		$msg.text( text )
			.removeClass( 'is-success is-error' );

		if ( type ) {
			$msg.addClass( 'is-' + type );
		}
	}

	/**
	 * Spustí / zastaví spinner.
	 *
	 * @param {jQuery}  $spinner  Spinner element.
	 * @param {boolean} active    true = zapnúť.
	 */
	function toggleSpinner( $spinner, active ) {
		if ( active ) {
			$spinner.addClass( 'is-active' );
		} else {
			$spinner.removeClass( 'is-active' );
		}
	}

	/* =========================================================
	   1. Pridanie nového filtra
	   ========================================================= */

	$( '#wc-sf-add-filter-btn' ).on( 'click', function () {
		var $btn     = $( this );
		var $spinner = $btn.siblings( '.wc-sf-spinner' );
		var $msg     = $btn.siblings( '.wc-sf-msg' );
		var type     = $( '#wc-sf-new-type' ).val();
		var metaKey  = $( '#wc-sf-new-meta-key' ).val();
		var style    = $( '#wc-sf-new-style' ).val();

		if ( ! type ) {
			showMsg( $msg, i18n.error, 'error' );
			return;
		}

		// Ak je vybraný meta_custom, použijeme meta_{key}.
		if ( 'meta_custom' === type ) {
			if ( ! metaKey ) {
				showMsg( $msg, i18n.error, 'error' );
				return;
			}
			type = 'meta_' + metaKey.replace( /\s+/g, '_' );
		}

		showMsg( $msg, i18n.saving, '' );
		toggleSpinner( $spinner, true );
		$btn.prop( 'disabled', true );

		$.post( ajax, {
			action:       'wc_sf_save_filter',
			nonce:        nonce,
			filter_type:  type,
			filter_style: style,
			label:        '',
			show_label:   1,
			config:       '{}'
		} )
		.done( function ( response ) {
			if ( ! response.success ) {
				showMsg( $msg, response.data && response.data.message ? response.data.message : i18n.error, 'error' );
				return;
			}

			showMsg( $msg, i18n.saved, 'success' );

			var filter  = response.data.filter;
			var $tbody  = $( '#wc-sf-sortable' );
			var $noRow  = $tbody.find( '.wc-sf-no-filters' );

			$noRow.remove();
			$tbody.append( buildFilterRow( filter ) );

			// Reset formulára.
			$( '#wc-sf-new-type' ).val( '' );
			$( '#wc-sf-new-meta-key' ).val( '' );
			$( '#wc-sf-meta-key-row' ).hide();
		} )
		.fail( function () {
			showMsg( $msg, i18n.error, 'error' );
		} )
		.always( function () {
			toggleSpinner( $spinner, false );
			$btn.prop( 'disabled', false );
		} );
	} );

	/**
	 * Zostaví HTML riadok pre nový filter.
	 *
	 * @param  {Object} filter  Dáta filtra.
	 * @return {string}         HTML riadok.
	 */
	function buildFilterRow( filter ) {
		var isFixed   = ( 'status' === filter.filter_type || 'sale' === filter.filter_type );
		var styleOpts = buildStyleOptions( filter.filter_type, filter.filter_style );
		var editUrl   = getEditUrl( filter.id );
		var label     = filter.label || '(bez názvu)';

		var disabled = isFixed ? ' disabled' : '';

		return '<tr class="wc-sf-filter-row" data-id="' + filter.id + '">' +
			'<td class="wc-sf-col-handle"><span class="wc-sf-drag-handle dashicons dashicons-move"></span></td>' +
			'<td>' + escHtml( label ) + '</td>' +
			'<td>' + escHtml( filter.filter_type ) + '</td>' +
			'<td><select class="wc-sf-style-select" data-filter-id="' + filter.id + '"' + disabled + '>' + styleOpts + '</select></td>' +
			'<td class="wc-sf-actions">' +
				'<a href="' + editUrl + '" class="button button-small" title="Nastavenia filtra"><span class="dashicons dashicons-admin-settings"></span></a> ' +
				'<button type="button" class="button button-small wc-sf-delete-filter" data-id="' + filter.id + '" title="Zmazať filter"><span class="dashicons dashicons-trash"></span></button>' +
			'</td>' +
		'</tr>';
	}

	/**
	 * Zostaví option elementy pre style-select podľa filter_type.
	 *
	 * @param  {string} filterType   Typ filtra.
	 * @param  {string} activeStyle  Aktívny štýl.
	 * @return {string}              HTML string.
	 */
	function buildStyleOptions( filterType, activeStyle ) {
		var styleLabels = {
			checkbox:       'Checkboxy',
			radio:          'Radio',
			dropdown:       'Dropdown',
			multi_dropdown: 'Multi-dropdown',
			slider:         'Posuvník'
		};

		var typeData  = types[ filterType ] || {};
		var allowed   = typeData.styles || Object.keys( styleLabels );
		var html      = '';

		allowed.forEach( function ( val ) {
			var selected = ( val === activeStyle ) ? ' selected' : '';
			html += '<option value="' + val + '"' + selected + '>' + ( styleLabels[ val ] || val ) + '</option>';
		} );

		return html;
	}

	/**
	 * Vráti URL pre editáciu filtra.
	 *
	 * @param  {number} id  ID filtra.
	 * @return {string}
	 */
	function getEditUrl( id ) {
		var base = ajaxurl.replace( 'admin-ajax.php', 'admin.php' );
		return base + '?page=wc-settings&tab=wc_sf&section=edit&filter_id=' + id;
	}

	/**
	 * Escapuje HTML špeciálne znaky.
	 *
	 * @param  {string} str  Vstupný reťazec.
	 * @return {string}
	 */
	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	/* =========================================================
	   2. Mazanie filtra
	   ========================================================= */

	$( '#wc-sf-sortable' ).on( 'click', '.wc-sf-delete-filter', function () {
		if ( ! window.confirm( i18n.confirmDelete ) ) {
			return;
		}

		var $btn    = $( this );
		var id      = $btn.data( 'id' );
		var $row    = $btn.closest( 'tr' );

		$btn.prop( 'disabled', true );

		$.post( ajax, {
			action: 'wc_sf_delete_filter',
			nonce:  nonce,
			id:     id
		} )
		.done( function ( response ) {
			if ( response.success ) {
				$row.fadeOut( 300, function () {
					$( this ).remove();

					// Ak je tabuľka prázdna, zobraz info riadok.
					if ( $( '#wc-sf-sortable tr' ).length === 0 ) {
						$( '#wc-sf-sortable' ).append(
							'<tr class="wc-sf-no-filters"><td colspan="5">Žiadne filtre. Pridajte prvý filter nižšie.</td></tr>'
						);
					}
				} );
			}
		} )
		.fail( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	/* =========================================================
	   3. Drag & drop zoradenie
	   ========================================================= */

	if ( $( '#wc-sf-sortable' ).length ) {
		$( '#wc-sf-sortable' ).sortable( {
			handle:      '.wc-sf-drag-handle',
			placeholder: 'wc-sf-filter-row ui-sortable-placeholder',
			axis:        'y',
			update: function () {
				var order = [];

				$( '#wc-sf-sortable .wc-sf-filter-row' ).each( function () {
					order.push( $( this ).data( 'id' ) );
				} );

				$.post( ajax, {
					action: 'wc_sf_reorder_filters',
					nonce:  nonce,
					order:  order
				} );
			}
		} );
	}

	/* =========================================================
	   4. Dynamický style-select — filtrovanie štýlov pri zmene
	      filter_type v "Pridať nový filter" formulári
	   ========================================================= */

	$( '#wc-sf-new-type' ).on( 'change', function () {
		var type      = $( this ).val();
		var $styleSelect = $( '#wc-sf-new-style' );
		var $metaRow  = $( '#wc-sf-meta-key-row' );

		// Zobraziť/skryť meta key field.
		if ( 'meta_custom' === type ) {
			$metaRow.show();
		} else {
			$metaRow.hide();
		}

		// Aktualizovať dostupné štýly.
		if ( ! type ) {
			return;
		}

		var typeData = types[ type ] || {};
		var fixed    = typeData.fixed_style;
		var allowed  = typeData.styles || [];
		var styleLabels = {
			checkbox:       'Checkboxy',
			radio:          'Radio',
			dropdown:       'Dropdown',
			multi_dropdown: 'Multi-dropdown',
			slider:         'Posuvník'
		};

		$styleSelect.empty();

		if ( fixed ) {
			$styleSelect.prop( 'disabled', true );
			$styleSelect.append( '<option value="' + fixed + '">' + ( styleLabels[ fixed ] || fixed ) + '</option>' );
		} else {
			$styleSelect.prop( 'disabled', false );
			allowed.forEach( function ( val ) {
				$styleSelect.append( '<option value="' + val + '">' + ( styleLabels[ val ] || val ) + '</option>' );
			} );
		}
	} );

	/* =========================================================
	   4b. Inline style-select zmena v tabuľke filtrov (live save)
	   ========================================================= */

	$( '#wc-sf-sortable' ).on( 'change', '.wc-sf-style-select', function () {
		var $select = $( this );
		var id      = $select.data( 'filter-id' );
		var style   = $select.val();

		$.post( ajax, {
			action:       'wc_sf_save_filter',
			nonce:        nonce,
			id:           id,
			filter_style: style
		} );
	} );

	/* =========================================================
	   5. Záložka Nastavenia — uloženie
	   ========================================================= */

	$( '#wc-sf-settings-form' ).on( 'submit', function ( e ) {
		e.preventDefault();

		var $btn     = $( '#wc-sf-save-settings-btn' );
		var $spinner = $btn.siblings( '.wc-sf-spinner' );
		var $msg     = $btn.siblings( '.wc-sf-msg' );
		var data     = $( this ).serializeArray();

		showMsg( $msg, i18n.saving, '' );
		toggleSpinner( $spinner, true );
		$btn.prop( 'disabled', true );

		// Pridaj action a nonce.
		var postData = { action: 'wc_sf_save_settings', nonce: nonce };
		data.forEach( function ( item ) {
			postData[ item.name ] = item.value;
		} );

		$.post( ajax, postData )
		.done( function ( response ) {
			if ( response.success ) {
				showMsg( $msg, response.data && response.data.message ? response.data.message : i18n.saved, 'success' );
			} else {
				showMsg( $msg, i18n.error, 'error' );
			}
		} )
		.fail( function () {
			showMsg( $msg, i18n.error, 'error' );
		} )
		.always( function () {
			toggleSpinner( $spinner, false );
			$btn.prop( 'disabled', false );
		} );
	} );

	/* =========================================================
	   5b. Reindex tlačidlo
	   ========================================================= */

	$( '#wc-sf-reindex-btn' ).on( 'click', function () {
		var $btn     = $( this );
		var $spinner = $btn.siblings( '.wc-sf-spinner' );
		var $msg     = $( '#wc-sf-reindex-msg' );

		showMsg( $msg, i18n.reindexing, '' );
		toggleSpinner( $spinner, true );
		$btn.prop( 'disabled', true );

		$.post( ajax, {
			action: 'wc_sf_reindex',
			nonce:  nonce
		} )
		.done( function ( response ) {
			if ( response.success ) {
				var text = response.data && response.data.message ? response.data.message : i18n.reindexDone;
				showMsg( $msg, text, 'success' );
			} else {
				showMsg( $msg, i18n.error, 'error' );
			}
		} )
		.fail( function () {
			showMsg( $msg, i18n.error, 'error' );
		} )
		.always( function () {
			toggleSpinner( $spinner, false );
			$btn.prop( 'disabled', false );
		} );
	} );

	/* =========================================================
	   6. Filter edit stránka — show/hide sekcií
	   ========================================================= */

	$( '#wc-sf-style' ).on( 'change', function () {
		updateEditSections( $( this ).val() );
	} );

	function updateEditSections( style ) {
		var $slider  = $( '.wc-sf-section-slider' );
		var $ranges  = $( '.wc-sf-section-ranges' );
		var $values  = $( '.wc-sf-section-values' );
		var $hideRow = $( '.wc-sf-row-hide-empty' );

		$slider.hide();
		$ranges.hide();

		if ( 'slider' === style ) {
			$slider.show();
			$values.hide();
			$hideRow.hide();
		} else {
			$values.show();
			$hideRow.show();
		}
	}

	/* =========================================================
	   7. Ranges repeater — pridávanie/mazanie
	   ========================================================= */

	var rangeIndex = $( '.wc-sf-range-row' ).length;

	$( '#wc-sf-add-range' ).on( 'click', function () {
		var $tbody = $( '#wc-sf-ranges-list' );
		var html   =
			'<tr class="wc-sf-range-row">' +
				'<td><input type="number" name="config[ranges][' + rangeIndex + '][min]" value="" class="small-text" step="any" /></td>' +
				'<td><input type="number" name="config[ranges][' + rangeIndex + '][max]" value="" class="small-text" step="any" placeholder="∞" /></td>' +
				'<td><input type="text" name="config[ranges][' + rangeIndex + '][label]" value="" class="regular-text" /></td>' +
				'<td><button type="button" class="button button-small wc-sf-remove-range">' + i18n.removeRow + '</button></td>' +
			'</tr>';

		$tbody.append( html );
		rangeIndex++;
	} );

	$( '#wc-sf-ranges-list' ).on( 'click', '.wc-sf-remove-range', function () {
		$( this ).closest( 'tr' ).remove();
	} );

	/* =========================================================
	   8. Values picker — načítanie dostupných hodnôt cez AJAX
	   ========================================================= */

	$( '#wc-sf-load-values' ).on( 'click', function () {
		var $btn        = $( this );
		var filterType  = $btn.data( 'filter-type' );
		var $spinner    = $btn.siblings( '.wc-sf-spinner' );
		var $container  = $( '#wc-sf-values-list' );

		toggleSpinner( $spinner, true );
		$btn.prop( 'disabled', true );

		$.post( ajax, {
			action:      'wc_sf_get_type_values',
			nonce:       nonce,
			filter_type: filterType
		} )
		.done( function ( response ) {
			if ( ! response.success ) {
				return;
			}

			var values      = response.data.values || [];
			var selected    = getSelectedValues( $container );
			var html        = '<div class="wc-sf-values-picker">';

			values.forEach( function ( item ) {
				var checked = selected.indexOf( item.value ) !== -1 ? ' checked' : '';
				var label   = item.label + ( item.count !== undefined ? ' (' + item.count + ')' : '' );
				html +=
					'<label>' +
						'<input type="checkbox" name="config[include_values][]" value="' + escHtml( item.value ) + '"' + checked + ' /> ' +
						escHtml( label ) +
					'</label>';
			} );

			html += '</div>';

			$container.html( html );
		} )
		.fail( function () {
			// Ticho zlyhanie — hodnoty ostanú ako sú.
		} )
		.always( function () {
			toggleSpinner( $spinner, false );
			$btn.prop( 'disabled', false );
		} );
	} );

	/**
	 * Získa aktuálne zaškrtnuté hodnoty z containera.
	 *
	 * @param  {jQuery} $container  Container s hodnotami.
	 * @return {string[]}
	 */
	function getSelectedValues( $container ) {
		var selected = [];
		$container.find( 'input[name="config[include_values][]"]' ).each( function () {
			if ( $( this ).is( ':checked' ) || $( this ).attr( 'type' ) === 'hidden' ) {
				selected.push( $( this ).val() );
			}
		} );
		return selected;
	}

	/* =========================================================
	   9. Edit formulár — AJAX uloženie
	   ========================================================= */

	$( '#wc-sf-edit-form' ).on( 'submit', function ( e ) {
		e.preventDefault();

		var $btn     = $( '#wc-sf-save-btn' );
		var $spinner = $btn.siblings( '.wc-sf-spinner' );
		var $msg     = $btn.siblings( '.wc-sf-msg' );

		showMsg( $msg, i18n.saving, '' );
		toggleSpinner( $spinner, true );
		$btn.prop( 'disabled', true );

		var formData = $( this ).serializeArray();
		var postData = { action: 'wc_sf_save_filter', nonce: nonce };

		formData.forEach( function ( item ) {
			// Mapuj serialized array na postData (config[...] → nested).
			postData[ item.name ] = item.value;
		} );

		$.post( ajax, postData )
		.done( function ( response ) {
			if ( response.success ) {
				showMsg( $msg, i18n.saved, 'success' );
			} else {
				var errMsg = response.data && response.data.message ? response.data.message : i18n.error;
				showMsg( $msg, errMsg, 'error' );
			}
		} )
		.fail( function () {
			showMsg( $msg, i18n.error, 'error' );
		} )
		.always( function () {
			toggleSpinner( $spinner, false );
			$btn.prop( 'disabled', false );
		} );
	} );

} )( jQuery );
