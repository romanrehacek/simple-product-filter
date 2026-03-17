/**
 * WC Simple Filter — Frontend JavaScript
 *
 * Phase 2a responsibilities (display):
 *  1. Sidebar: collapsible filter sections.
 *  2. Horizontal: pill-button open/close of filter dropdown panels.
 *  3. "View more / View less" for checkbox and radio lists.
 *  4. jQuery UI Slider initialisation.
 *
 * Phase 2b responsibilities (filtering):
 *  5. Collect filter state from all wcsf inputs.
 *  6. Dispatch filter via ajax / submit / reload mode.
 *  7. AJAX: POST to wc_sf_filter_products, swap product HTML.
 *  8. Reload: build URL with wcsf[] params, navigate.
 *  9. Submit: native form submit (GET).
 * 10. Push/pop state for browser history (ajax + reload modes).
 * 11. Active filter chips (horizontal layout).
 * 12. Reset / clear all filters.
 * 13. Pagination intercept in AJAX mode.
 * 14. Debounce (300 ms) on filter changes.
 *
 * @package WC_Simple_Filter
 */

/* global jQuery, WC_SF_Frontend */
( function ( $ ) {
	'use strict';

	// Config injected via wp_localize_script().
	var cfg = ( typeof WC_SF_Frontend !== 'undefined' ) ? WC_SF_Frontend : {};

	// Debounce timer handle.
	var debounceTimer = null;

	// Container for swapped product HTML (cached after first lookup).
	var $productsWrap = null;

	// Central filter state — single source of truth shared across ALL root instances.
	// Updated exclusively via setState(); never written to directly.
	var activeState = {};

	// All registered .wcsf root elements — populated during boot.
	// Used by setState() to broadcast state changes to every root (sidebar, horizontal, chips).
	var allRoots = [];

	/* =========================================================================
	   1. Sidebar — collapsible sections
	   ========================================================================= */

	/**
	 * Toggle a collapsible filter body.
	 *
	 * @param {HTMLElement} btn    The .wcsf__filter-toggle button.
	 * @param {boolean}     [force] If supplied, true = open, false = close.
	 */
	function toggleSidebarFilter( btn, force ) {
		var expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
		var open     = ( typeof force !== 'undefined' ) ? force : ! expanded;
		var bodyId   = btn.getAttribute( 'aria-controls' );
		var body     = document.getElementById( bodyId );
		var wrapper  = btn.closest( '.wcsf__filter' );

		if ( ! body || ! wrapper ) {
			return;
		}

		btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		body.setAttribute( 'aria-hidden', open ? 'false' : 'true' );

		if ( open ) {
			wrapper.classList.remove( 'wcsf__filter--collapsed' );
			body.style.display = '';
		} else {
			wrapper.classList.add( 'wcsf__filter--collapsed' );
			body.style.display = 'none';
		}
	}

	/**
	 * Initialise sidebar collapsible behaviour.
	 *
	 * @param {HTMLElement} root  .wcsf--sidebar element.
	 */
	function initSidebar( root ) {
		root.querySelectorAll( '.wcsf__filter-toggle' ).forEach( function ( btn ) {
			var isExpanded = btn.getAttribute( 'aria-expanded' ) === 'true';
			var bodyId     = btn.getAttribute( 'aria-controls' );
			var body       = document.getElementById( bodyId );

			if ( body && ! isExpanded ) {
				body.style.display = 'none';
			}

			btn.addEventListener( 'click', function () {
				toggleSidebarFilter( this );
			} );
		} );
	}

	/* =========================================================================
	   2. Horizontal — pill dropdown panels
	   ========================================================================= */

	/**
	 * Close all open filter panels inside a horizontal wrapper.
	 *
	 * @param {HTMLElement} root    .wcsf--horizontal element.
	 * @param {HTMLElement} [except] Optional pill to skip (keep open).
	 */
	function closeAllPanels( root, except ) {
		root.querySelectorAll( '.wcsf__toggle-pill[aria-expanded="true"]' ).forEach( function ( pill ) {
			if ( pill !== except ) {
				var filterId = pill.getAttribute( 'data-filter-id' );
				var panel    = root.querySelector( '.wcsf__filter[data-filter-id="' + filterId + '"]' );

				pill.setAttribute( 'aria-expanded', 'false' );
				if ( panel ) {
					panel.classList.remove( 'wcsf__filter--open' );
				}
			}
		} );
	}

	/**
	 * Initialise horizontal pill-toggle behaviour.
	 *
	 * @param {HTMLElement} root  .wcsf--horizontal element.
	 */
	function initHorizontal( root ) {
		root.querySelectorAll( '.wcsf__toggle-pill' ).forEach( function ( pill ) {
			pill.addEventListener( 'click', function ( e ) {
				e.stopPropagation();

				var filterId = this.getAttribute( 'data-filter-id' );
				var panel    = root.querySelector( '.wcsf__filter[data-filter-id="' + filterId + '"]' );
				var isOpen   = this.getAttribute( 'aria-expanded' ) === 'true';

				closeAllPanels( root, this );

				if ( isOpen ) {
					this.setAttribute( 'aria-expanded', 'false' );
					if ( panel ) {
						panel.classList.remove( 'wcsf__filter--open' );
					}
				} else {
					this.setAttribute( 'aria-expanded', 'true' );
					if ( panel ) {
						panel.classList.add( 'wcsf__filter--open' );
						positionPanel( panel, this );
					}
				}
			} );
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.wcsf__filter' ) ) {
				return;
			}
			closeAllPanels( root );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				closeAllPanels( root );
			}
		} );
	}

	/**
	 * Position a panel horizontally under its pill button.
	 *
	 * @param {HTMLElement} panel  .wcsf__filter element.
	 * @param {HTMLElement} pill   .wcsf__toggle-pill button.
	 */
	function positionPanel( panel, pill ) {
		if ( window.innerWidth <= 600 ) {
			panel.style.left = '';
			return;
		}

		var filtersEl  = panel.parentElement;
		var wcsfRoot   = filtersEl ? filtersEl.parentElement : null;

		if ( ! filtersEl || ! wcsfRoot ) {
			return;
		}

		var pillRect   = pill.getBoundingClientRect();
		var rootRect   = wcsfRoot.getBoundingClientRect();
		var leftOffset = pillRect.left - rootRect.left;
		var panelWidth = panel.offsetWidth || 220;
		var maxLeft    = wcsfRoot.offsetWidth - panelWidth - 4;

		panel.style.left = Math.max( 0, Math.min( leftOffset, maxLeft ) ) + 'px';
	}

	/* =========================================================================
	   3. View more / View less
	   ========================================================================= */

	/**
	 * Initialise "View more / View less" buttons.
	 *
	 * @param {HTMLElement} root  .wcsf element.
	 */
	function initViewMore( root ) {
		root.querySelectorAll( '.wcsf__view-more-link' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var list        = this.previousElementSibling;
				var isExpanded  = this.getAttribute( 'aria-expanded' ) === 'true';
				var visible     = parseInt( this.getAttribute( 'data-visible' ), 10 );
				var total       = parseInt( this.getAttribute( 'data-total' ), 10 );
				var hiddenClass = '';

				if ( ! list ) {
					return;
				}

				if ( list.classList.contains( 'wcsf__checkbox-list' ) ) {
					hiddenClass = 'wcsf__checkbox-item--hidden';
				} else if ( list.classList.contains( 'wcsf__radio-list' ) ) {
					hiddenClass = 'wcsf__radio-item--hidden';
				}

				if ( ! hiddenClass ) {
					return;
				}

				if ( isExpanded ) {
					list.querySelectorAll( 'li' ).forEach( function ( li, idx ) {
						if ( idx >= visible ) {
							li.classList.add( hiddenClass );
						}
					} );
					this.setAttribute( 'aria-expanded', 'false' );
					this.textContent = wcsfViewMoreLabel( total - visible );
				} else {
					list.querySelectorAll( 'li' ).forEach( function ( li ) {
						li.classList.remove( hiddenClass );
					} );
					this.setAttribute( 'aria-expanded', 'true' );
					this.textContent = cfg.i18n ? cfg.i18n.viewLess : 'Zobraziť menej';
				}
			} );
		} );
	}

	/**
	 * Build localised "Zobraziť viac (N)" label.
	 *
	 * @param  {number} count
	 * @return {string}
	 */
	function wcsfViewMoreLabel( count ) {
		if ( cfg.i18n && cfg.i18n.viewMore ) {
			return cfg.i18n.viewMore + ' (' + count + ')';
		}
		return 'Zobraziť viac (' + count + ')';
	}

	/* =========================================================================
	   4. jQuery UI Slider
	   ========================================================================= */

	/**
	 * Initialise all range sliders on the page.
	 * Slide callbacks update hidden inputs and trigger filter dispatch (Phase 2b).
	 *
	 * @param {HTMLElement} root  .wcsf element.
	 */
	function initSliders( root ) {
		if ( typeof $.fn.slider === 'undefined' ) {
			return;
		}

		root.querySelectorAll( '.wcsf__slider-wrapper' ).forEach( function ( wrapper ) {
			var sliderEl = wrapper.querySelector( '.wcsf__slider' );

			if ( ! sliderEl ) {
				return;
			}

			var min  = parseFloat( wrapper.getAttribute( 'data-min' ) ) || 0;
			var max  = parseFloat( wrapper.getAttribute( 'data-max' ) ) || 1000;
			var step = parseFloat( wrapper.getAttribute( 'data-step' ) ) || 1;

			var $minVal = $( wrapper ).find( '.wcsf__slider-value--min' );
			var $maxVal = $( wrapper ).find( '.wcsf__slider-value--max' );
			var $minIn  = $( wrapper ).find( '.wcsf__slider-input--min' );
			var $maxIn  = $( wrapper ).find( '.wcsf__slider-input--max' );

			$( sliderEl ).slider( {
				range:  true,
				min:    min,
				max:    max,
				step:   step,
				values: [ min, max ],
				slide: function ( event, ui ) {
					$minVal.text( ui.values[ 0 ] );
					$maxVal.text( ui.values[ 1 ] );
					$minIn.val( ui.values[ 0 ] );
					$maxIn.val( ui.values[ 1 ] );

					// Build new state with updated slider key, then route through setState.
					var filterType = wrapper.getAttribute( 'data-filter-type' );

					if ( filterType ) {
						var newState = Object.assign( {}, activeState );

						// Only add if values differ from slider defaults (keeps state clean).
						var dataMinStr = String( parseFloat( wrapper.getAttribute( 'data-min' ) ) || 0 );
						var dataMaxStr = String( parseFloat( wrapper.getAttribute( 'data-max' ) ) || 1000 );
						var newMinStr  = String( ui.values[ 0 ] );
						var newMaxStr  = String( ui.values[ 1 ] );

						if ( newMinStr === dataMinStr && newMaxStr === dataMaxStr ) {
							delete newState[ filterType ];
						} else {
							newState[ filterType ] = { min: ui.values[ 0 ], max: ui.values[ 1 ] };
						}

					// Dispatch via setState with debounce; applyStateToInputs is skipped for
					// the originating root because the slider widget already updated the hidden
					// inputs above. Other roots will still be synced by setState's broadcast.
					clearTimeout( debounceTimer );
					debounceTimer = setTimeout( function () {
						setState( root, newState, { dispatch: true, syncInputs: false } );
					}, 300 );
					}
				},
			} );
		} );
	}

	/* =========================================================================
	   5. Collect filter state
	   ========================================================================= */

	/**
	 * Read all wcsf[] input values inside root and return a plain object.
	 *
	 * Returns:  { brand: ['nike','adidas'], price: {min:'0',max:'500'}, ... }
	 *
	 * @param  {HTMLElement} root  .wcsf element.
	 * @return {Object}
	 */
	function collectFilterState( root ) {
		var state   = {};
		var form    = root.querySelector( '.wcsf__form' );
		var inputs  = ( form || root ).querySelectorAll( 'input[name^="wcsf"], select[name^="wcsf"]' );

		inputs.forEach( function ( el ) {
			// Parse name: wcsf[brand][] → key='brand', isArray=true
			//             wcsf[price][min] → key='price', subKey='min'
			var match = el.name.match( /^wcsf\[([^\]]+)\](?:\[([^\]]*)\])?/ );

			if ( ! match ) {
				return;
			}

			var key    = match[ 1 ];
			var subKey = match[ 2 ];  // 'min', 'max', '' (array), or undefined

			// Skip unchecked checkboxes / radios.
			if ( ( el.type === 'checkbox' || el.type === 'radio' ) && ! el.checked ) {
				return;
			}

			var val = el.value;

			if ( typeof subKey !== 'undefined' ) {
				// Sub-keyed (slider min/max): wcsf[price][min]
				if ( '' === subKey ) {
					// wcsf[key][] — array item.
					if ( ! state[ key ] ) {
						state[ key ] = [];
					}
					if ( val !== '' ) {
						state[ key ].push( val );
					}
				} else {
					// wcsf[key][subKey] — object item.
					// Only initialise / write when value is non-empty.
					if ( val !== '' ) {
						if ( ! state[ key ] || Array.isArray( state[ key ] ) ) {
							state[ key ] = {};
						}
						state[ key ][ subKey ] = val;
					}
				}
			} else {
				// Scalar: wcsf[key].
				if ( val !== '' ) {
					state[ key ] = val;
				}
			}
		} );

		// For select[multiple], jQuery serialise helps; handle via native.
		root.querySelectorAll( 'select[name^="wcsf"][multiple]' ).forEach( function ( sel ) {
			var match = sel.name.match( /^wcsf\[([^\]]+)\]/ );

			if ( ! match ) {
				return;
			}

			var key     = match[ 1 ];
			var selected = Array.from( sel.options )
				.filter( function ( o ) { return o.selected && o.value !== ''; } )
				.map( function ( o ) { return o.value; } );

			if ( selected.length ) {
				state[ key ] = selected;
			} else {
				delete state[ key ];
			}
		} );

		// Drop slider keys whose values match the slider's data-min / data-max defaults
		// (i.e. the slider was never moved — treat as inactive).
		root.querySelectorAll( '.wcsf__slider-wrapper' ).forEach( function ( wrapper ) {
			var filterType = wrapper.getAttribute( 'data-filter-type' );

			if ( ! filterType || ! state[ filterType ] || Array.isArray( state[ filterType ] ) ) {
				return;
			}

			var sliderState = state[ filterType ];
			var dataMin     = String( parseFloat( wrapper.getAttribute( 'data-min' ) ) || 0 );
			var dataMax     = String( parseFloat( wrapper.getAttribute( 'data-max' ) ) || 1000 );

			var stateMin = sliderState.min !== undefined ? String( sliderState.min ) : null;
			var stateMax = sliderState.max !== undefined ? String( sliderState.max ) : null;

			// Both present and both equal defaults → remove (slider untouched).
			if ( stateMin === dataMin && stateMax === dataMax ) {
				delete state[ filterType ];
			}
		} );

		return state;
	}

	/* =========================================================================
	   6. URL helpers
	   ========================================================================= */

	/**
	 * Build a query string from the filter state.
	 * Produces: wcsf[brand][]=nike&wcsf[brand][]=adidas&wcsf[price][min]=100
	 *
	 * @param  {Object} state  Output of collectFilterState().
	 * @param  {number} [paged] Page number (default 1 = omit).
	 * @return {string}  Query string without leading '?'.
	 */
	function buildFilterQueryString( state, paged ) {
		var pairs = [];

		Object.keys( state ).forEach( function ( key ) {
			var val = state[ key ];

			if ( Array.isArray( val ) ) {
				val.forEach( function ( v ) {
					pairs.push( encodeURIComponent( 'wcsf[' + key + '][]' ) + '=' + encodeURIComponent( v ) );
				} );
			} else if ( val !== null && typeof val === 'object' ) {
				Object.keys( val ).forEach( function ( subKey ) {
					pairs.push(
						encodeURIComponent( 'wcsf[' + key + '][' + subKey + ']' ) + '=' + encodeURIComponent( val[ subKey ] )
					);
				} );
			} else {
				pairs.push( encodeURIComponent( 'wcsf[' + key + ']' ) + '=' + encodeURIComponent( val ) );
			}
		} );

		if ( paged && paged > 1 ) {
			pairs.push( 'paged=' + paged );
		}

		// Preserve non-wcsf params (e.g. orderby, product category slug, post_type).
		var currentParams = new URLSearchParams( window.location.search );

		currentParams.forEach( function ( v, k ) {
			if ( k !== 'paged' && ! k.startsWith( 'wcsf' ) ) {
				pairs.push( encodeURIComponent( k ) + '=' + encodeURIComponent( v ) );
			}
		} );

		return pairs.join( '&' );
	}

	/**
	 * Read filter state from the current URL (?wcsf[...]) and pre-populate inputs.
	 * Called on page load to restore state after reload/popstate.
	 * Products are already server-rendered, so dispatch is skipped.
	 *
	 * @param {HTMLElement} root  .wcsf element.
	 */
	function readStateFromUrl( root ) {
		var params  = new URLSearchParams( window.location.search );
		var urlState = {};

		params.forEach( function ( val, rawKey ) {
			// Decode bracket notation: wcsf%5Bbrand%5D%5B%5D → wcsf[brand][].
			var key = decodeURIComponent( rawKey );

			if ( ! key.startsWith( 'wcsf[' ) ) {
				return;
			}

			// Parse: wcsf[brand][]  → filterKey='brand', subKey=''
			//        wcsf[price][min] → filterKey='price', subKey='min'
			//        wcsf[color]     → filterKey='color',  subKey=undefined
			var matchKey = key.match( /^wcsf\[([^\]]+)\](?:\[([^\]]*)\])?/ );

			if ( ! matchKey ) {
				return;
			}

			var filterKey = matchKey[ 1 ];
			var subKey    = matchKey[ 2 ]; // '', 'min', 'max', or undefined.

			if ( typeof subKey !== 'undefined' ) {
				if ( subKey === '' ) {
					// Array item: wcsf[brand][].
					if ( ! Array.isArray( urlState[ filterKey ] ) ) {
						urlState[ filterKey ] = [];
					}
					urlState[ filterKey ].push( val );
				} else {
					// Sub-keyed (slider): wcsf[price][min].
					if ( ! urlState[ filterKey ] || Array.isArray( urlState[ filterKey ] ) ) {
						urlState[ filterKey ] = {};
					}
					urlState[ filterKey ][ subKey ] = val;
				}
			} else {
				// Scalar.
				urlState[ filterKey ] = val;
			}
		} );

		if ( Object.keys( urlState ).length ) {
			// Products are already server-rendered — only sync inputs/chips/panels.
			setState( root, urlState, { dispatch: false } );
		}
	}

	/* =========================================================================
	   7. Dispatch — ajax / reload / submit
	   ========================================================================= */

	/**
	 * Main dispatcher — routes to ajax / reload / submit.
	 *
	 * @param {HTMLElement} root   .wcsf element.
	 * @param {Object}      state  Filter state.
	 * @param {number}      [paged] Page (default 1).
	 */
	function dispatchFilter( root, state, paged ) {
		paged = paged || 1;
		var mode = root.getAttribute( 'data-filter-mode' ) || cfg.filterMode || 'ajax';

		if ( mode === 'submit' ) {
			// Native form submit — browser handles navigation.
			doSubmitFilter( root );
		} else if ( mode === 'reload' ) {
			doReloadFilter( state, paged );
		} else {
			doAjaxFilter( root, state, paged );
		}
	}

	/**
	 * Submit mode: trigger native form GET submit.
	 *
	 * @param {HTMLElement} root  .wcsf element.
	 */
	function doSubmitFilter( root ) {
		var form = root.querySelector( '.wcsf__form' );

		if ( form ) {
			form.submit();
		}
	}

	/**
	 * Reload mode: build URL and navigate.
	 *
	 * @param {Object} state  Filter state.
	 * @param {number} paged  Page number.
	 */
	function doReloadFilter( state, paged ) {
		var qs  = buildFilterQueryString( state, paged );
		var url = window.location.pathname + ( qs ? '?' + qs : '' );

		pushFilterState( state, paged, url );
		window.location.href = url;
	}

	/**
	 * AJAX mode: POST to wc_sf_filter_products and swap product HTML.
	 *
	 * @param {HTMLElement} root   .wcsf element.
	 * @param {Object}      state  Filter state.
	 * @param {number}      paged  Page number.
	 */
	function doAjaxFilter( root, state, paged ) {
		var $products = getProductsWrapper();

		if ( ! $products || ! $products.length ) {
			// If we can not find the product wrapper fall back to reload.
			doReloadFilter( state, paged );
			return;
		}

		// Loading state.
		$products.addClass( 'wcsf--loading' );
		root.classList.add( 'wcsf--loading' );

		// Flatten state for POST — jQuery.ajax will not serialise nested objects
		// unless we do it manually. We send wcsf as a nested object and let
		// WordPress decode it (php://input / $_POST).
		var postData = {
			action:  'wc_sf_filter_products',
			nonce:   cfg.nonce || '',
			paged:   paged,
			orderby: getCurrentOrderby(),
			wcsf:    state,
		};

		var replaced = false;

		$.ajax( {
			url:    cfg.ajaxUrl || '/wp-admin/admin-ajax.php',
			type:   'POST',
			data:   postData,
			success: function ( response ) {
				if ( ! response.success ) {
					$products.removeClass( 'wcsf--loading' );
					root.classList.remove( 'wcsf--loading' );
					return;
				}

				var data = response.data;

				// Swap product HTML.
				// The AJAX response renders the full loop wrapper (loop-start + items + loop-end),
				// so we replace the wrapper entirely rather than setting its innerHTML.
				$products.replaceWith( data.html || '' );
				replaced = true;
				// Clear cached reference so next call re-acquires the new DOM node.
				$productsWrap = null;
				var $newProducts = getProductsWrapper();

				// Swap pagination HTML.
				// Use the outermost pagination container if present; fall back to the nav.
				var $pagination = $( '.child-category__pagination, nav.woocommerce-pagination, .woocommerce-pagination, .wcsf-pagination' ).first();

				if ( $pagination.length ) {
					$pagination.replaceWith( data.pagination || '' );
				} else if ( data.pagination && $newProducts ) {
					$newProducts.after( data.pagination );
				}

				// Re-bind pagination for AJAX mode.
				initAjaxPagination( root );

				// Update URL without reload.
				pushFilterState( state, data.paged || paged );

				// Update active chips.
				updateActiveChips( root, state );

				// Trigger WC JS re-init hooks (e.g. variation swatches).
				$( document.body ).trigger( 'wc_fragments_loaded' );
				$( document.body ).trigger( 'wcsf_products_updated', [ data ] );
			},
			error: function () {
				// On network error just reload.
				doReloadFilter( state, paged );
			},
			complete: function () {
				// $products may be detached if replaceWith ran; use new wrapper if available.
				if ( replaced ) {
					var $cur = getProductsWrapper();
					if ( $cur ) {
						$cur.removeClass( 'wcsf--loading' );
					}
				} else {
					$products.removeClass( 'wcsf--loading' );
				}
				root.classList.remove( 'wcsf--loading' );
			},
		} );
	}

	/* =========================================================================
	   8. History push/pop
	   ========================================================================= */

	/**
	 * Push the current filter state to browser history.
	 *
	 * @param {Object} state   Filter state.
	 * @param {number} [paged] Page number.
	 * @param {string} [url]   Override URL (default: build from state).
	 */
	function pushFilterState( state, paged, url ) {
		if ( ! window.history || ! window.history.pushState ) {
			return;
		}

		paged = paged || 1;
		url   = url || ( window.location.pathname + ( function () {
			var qs = buildFilterQueryString( state, paged );
			return qs ? '?' + qs : '';
		}() ) );

		window.history.pushState( { wcsf: state, paged: paged }, '', url );
	}

	/**
	 * Restore filter state on browser back/forward.
	 *
	 * @param {HTMLElement} root  .wcsf element.
	 */
	function initPopState( root ) {
		window.addEventListener( 'popstate', function ( e ) {
			if ( e.state && e.state.wcsf ) {
				restoreState( root, e.state.wcsf, e.state.paged || 1 );
			} else {
				// No state — clear all inputs and reload.
				clearAllInputs( root );
				doAjaxFilter( root, {}, 1 );
			}
		} );
	}

	/**
	 * Restore filter inputs from a state object and re-run AJAX.
	 * Called by popstate handler when navigating browser history.
	 *
	 * @param {HTMLElement} root   .wcsf element.
	 * @param {Object}      state  Filter state.
	 * @param {number}      paged  Page number.
	 */
	function restoreState( root, state, paged ) {
		setState( root, state, { dispatch: true, paged: paged } );
	}

	/* =========================================================================
	   9. Active chips (horizontal layout)
	   ========================================================================= */

	/**
	 * Look up the human-readable label for a filter value from the DOM.
	 *
	 * Searches all registered roots so that a chip rendered in a horizontal bar
	 * can resolve labels from inputs that live only in the sidebar, and vice versa.
	 *
	 * Search order per root:
	 *  1. Checkbox/radio: input[value=slug] → closest label → .wcsf__checkbox-text / .wcsf__radio-text / .wcsf__color-text
	 *  2. Select option: option[value=slug] text
	 *  3. Fallback: raw value (slug).
	 *
	 * @param  {HTMLElement} root       .wcsf element (used as starting point; all roots are searched).
	 * @param  {string}      filterKey  e.g. 'brand'.
	 * @param  {string}      value      Raw input value (slug).
	 * @return {string}  Human-readable label.
	 */
	function getLabelForValue( root, filterKey, value ) {
		// Build a de-duplicated list: start with the provided root, then add the rest.
		var roots = [ root ];
		allRoots.forEach( function ( r ) {
			if ( r !== root ) {
				roots.push( r );
			}
		} );

		var inputSel = 'input[name="wcsf[' + filterKey + '][]"][value="' + value.replace( /"/g, '\\"' ) + '"],' +
		               'input[name="wcsf[' + filterKey + ']"][value="'   + value.replace( /"/g, '\\"' ) + '"]';

		for ( var ri = 0; ri < roots.length; ri++ ) {
			var r = roots[ ri ];

			// 1. Checkbox / radio input match.
			try {
				var inp = r.querySelector( inputSel );

				if ( inp ) {
					var lbl = inp.closest( 'label' );

					if ( lbl ) {
						var textEl = lbl.querySelector( '.wcsf__checkbox-text, .wcsf__radio-text, .wcsf__color-text' );

						if ( textEl ) {
							return textEl.textContent.trim();
						}

						// Fallback: full label text minus any nested hidden content.
						return lbl.textContent.trim();
					}
				}
			} catch ( e ) {
				// CSS selector errors — fall through.
			}

			// 2. Select option match.
			var selEls = r.querySelectorAll(
				'select[name="wcsf[' + filterKey + '][]"], select[name="wcsf[' + filterKey + ']"]'
			);

			for ( var i = 0; i < selEls.length; i++ ) {
				var opt = selEls[ i ].querySelector( 'option[value="' + value.replace( /"/g, '\\"' ) + '"]' );

				if ( opt ) {
					return opt.textContent.trim();
				}
			}
		}

		// 3. Fallback to raw value.
		return value;
	}

	/**
	 * Rebuild the active filter chips bar.
	 *
	 * Each chip shows a filter value and has an × button to remove it.
	 *
	 * @param {HTMLElement} root   .wcsf element (the horizontal wrapper).
	 * @param {Object}      state  Current filter state.
	 */
	function updateActiveChips( root, state ) {
		var chipsEl = root.querySelector( '.wcsf__active-bar-chips' );
		var bar     = root.querySelector( '.wcsf__active-bar' );

		if ( ! chipsEl ) {
			return;
		}

		chipsEl.innerHTML = '';

		var count = 0;

		Object.keys( state ).forEach( function ( key ) {
			var val = state[ key ];

			if ( Array.isArray( val ) ) {
				val.forEach( function ( v ) {
					var humanLabel = getLabelForValue( root, key, v );
					chipsEl.appendChild( buildChip( root, key, humanLabel, v, null ) );
					count++;
				} );
			} else if ( val !== null && typeof val === 'object' ) {
				// Slider chip — show "min – max".
				var label = '';

				if ( val.min !== undefined && val.max !== undefined ) {
					label = val.min + ' – ' + val.max;
				} else if ( val.min !== undefined ) {
					label = '≥ ' + val.min;
				} else if ( val.max !== undefined ) {
					label = '≤ ' + val.max;
				}

				if ( label ) {
					chipsEl.appendChild( buildChip( root, key, label, label, key ) );
					count++;
				}
			} else {
				var humanLabel = getLabelForValue( root, key, val );
				chipsEl.appendChild( buildChip( root, key, humanLabel, val, null ) );
				count++;
			}
		} );

		if ( bar ) {
			if ( count > 0 ) {
				bar.classList.add( 'wcsf__active-bar--visible' );
			} else {
				bar.classList.remove( 'wcsf__active-bar--visible' );
			}
		}
	}

	/**
	 * Build a single active-filter chip element.
	 *
	 * @param {HTMLElement}  root         .wcsf element.
	 * @param {string}       filterKey    e.g. 'brand'.
	 * @param {string}       displayVal   Human-readable label (shown in chip).
	 * @param {string}       rawVal       Raw input value (used for uncheck logic).
	 * @param {string|null}  removeTarget If set, removes the whole key (for slider).
	 * @return {HTMLElement}
	 */
	function buildChip( root, filterKey, displayVal, rawVal, removeTarget ) {
		var chip     = document.createElement( 'span' );
		chip.className = 'wcsf__active-chip';

		var label    = document.createElement( 'span' );
		label.textContent = displayVal;
		chip.appendChild( label );

		var btn      = document.createElement( 'button' );
		btn.type     = 'button';
		btn.className = 'wcsf__active-chip-remove';
		btn.setAttribute( 'aria-label', ( cfg.i18n && cfg.i18n.removeFilter ? cfg.i18n.removeFilter : 'Odstrániť' ) + ': ' + displayVal );
		btn.textContent = '×';

		btn.addEventListener( 'click', function () {
			var newState = Object.assign( {}, activeState );

			if ( removeTarget ) {
				// Remove entire key (slider).
				delete newState[ removeTarget ];

				// Also reset slider widget handles to full range.
				var sliderWrapper = root.querySelector( '.wcsf__slider-wrapper[data-filter-type="' + removeTarget + '"]' );

				if ( sliderWrapper ) {
					var $sl      = $( sliderWrapper ).find( '.wcsf__slider' );
					var fullMin  = parseFloat( sliderWrapper.getAttribute( 'data-min' ) ) || 0;
					var fullMax  = parseFloat( sliderWrapper.getAttribute( 'data-max' ) ) || 1000;

					if ( $sl.length && typeof $sl.slider === 'function' ) {
						$sl.slider( 'values', [ fullMin, fullMax ] );
						$( sliderWrapper ).find( '.wcsf__slider-value--min' ).text( fullMin );
						$( sliderWrapper ).find( '.wcsf__slider-value--max' ).text( fullMax );
					}
				}
			} else {
				// Remove a single value from array or scalar key.
				var existing = newState[ filterKey ];

				if ( Array.isArray( existing ) ) {
					var filtered = existing.filter( function ( v ) { return v !== rawVal; } );

					if ( filtered.length ) {
						newState[ filterKey ] = filtered;
					} else {
						delete newState[ filterKey ];
					}
				} else {
					delete newState[ filterKey ];
				}
			}

			setState( root, newState, { dispatch: true } );
		} );

		chip.appendChild( btn );

		return chip;
	}

	/* =========================================================================
	   10. Reset / clear all
	   ========================================================================= */

	/**
	 * Uncheck all filter inputs and reset select/text elements.
	 *
	 * @param {HTMLElement} root  .wcsf element.
	 */
	function clearAllInputs( root ) {
		root.querySelectorAll( 'input[name^="wcsf"]' ).forEach( function ( inp ) {
			if ( inp.type === 'checkbox' || inp.type === 'radio' ) {
				inp.checked = false;
			} else {
				inp.value = '';
			}
		} );

		root.querySelectorAll( 'select[name^="wcsf"]' ).forEach( function ( sel ) {
			Array.from( sel.options ).forEach( function ( o ) {
				o.selected = false;
			} );
		} );
	}

	/**
	 * Apply a state object to all DOM inputs inside root.
	 * Calls clearAllInputs first, then sets each input to match state.
	 * Also syncs jQuery UI slider handles and display spans.
	 *
	 * @param {HTMLElement} root   .wcsf element.
	 * @param {Object}      state  State produced by collectFilterState().
	 */
	function applyStateToInputs( root, state ) {
		clearAllInputs( root );

		Object.keys( state ).forEach( function ( key ) {
			var val = state[ key ];

			if ( Array.isArray( val ) ) {
				// Checkbox / color (name="wcsf[key][]") or radio array.
				val.forEach( function ( v ) {
					root.querySelectorAll(
						'input[name="wcsf[' + key + '][]"][value="' + v.replace( /"/g, '\\"' ) + '"]'
					).forEach( function ( inp ) {
						inp.checked = true;
					} );
				} );
			} else if ( val !== null && typeof val === 'object' ) {
				// Slider: { min, max } → hidden inputs + jQuery UI widget.
				var wrapper = root.querySelector( '.wcsf__slider-wrapper[data-filter-type="' + key + '"]' );

				if ( val.min !== undefined ) {
					var minInp = root.querySelector( 'input[name="wcsf[' + key + '][min]"]' );

					if ( minInp ) {
						minInp.value = val.min;
					}
				}

				if ( val.max !== undefined ) {
					var maxInp = root.querySelector( 'input[name="wcsf[' + key + '][max]"]' );

					if ( maxInp ) {
						maxInp.value = val.max;
					}
				}

				// Update jQuery UI slider handle positions and display values.
				if ( wrapper ) {
					var $slider = $( wrapper ).find( '.wcsf__slider' );

					if ( $slider.length && typeof $slider.slider === 'function' ) {
						var sliderMin = parseFloat( wrapper.getAttribute( 'data-min' ) ) || 0;
						var sliderMax = parseFloat( wrapper.getAttribute( 'data-max' ) ) || 1000;
						var newMin    = val.min !== undefined ? parseFloat( val.min ) : sliderMin;
						var newMax    = val.max !== undefined ? parseFloat( val.max ) : sliderMax;

						$slider.slider( 'values', [ newMin, newMax ] );
						$( wrapper ).find( '.wcsf__slider-value--min' ).text( newMin );
						$( wrapper ).find( '.wcsf__slider-value--max' ).text( newMax );
					}
				}
			} else if ( val !== null && val !== '' ) {
				// Scalar: radio (name="wcsf[key]") or single select (name="wcsf[key]").
				var radioInp = root.querySelector(
					'input[name="wcsf[' + key + ']"][value="' + String( val ).replace( /"/g, '\\"' ) + '"]'
				);

				if ( radioInp ) {
					radioInp.checked = true;
				} else {
					var selEl = root.querySelector( 'select[name="wcsf[' + key + ']"]' );

					if ( selEl ) {
						selEl.value = val;
					}
				}
			}
		} );

		// Multi-select: iterate options.
		root.querySelectorAll( 'select[name^="wcsf"][multiple]' ).forEach( function ( sel ) {
			var match = sel.name.match( /^wcsf\[([^\]]+)\]/ );

			if ( ! match ) {
				return;
			}

			var key = match[ 1 ];
			var val = state[ key ];

			if ( ! Array.isArray( val ) ) {
				return;
			}

			Array.from( sel.options ).forEach( function ( opt ) {
				opt.selected = val.indexOf( opt.value ) !== -1;
			} );
		} );
	}

	/**
	 * Central state hub — the ONLY way to change filter state.
	 *
	 * Updates activeState, syncs DOM inputs, syncs chips, and (optionally)
	 * dispatches a filter request.
	 *
	 * When multiple .wcsf roots exist on the page (e.g. sidebar + horizontal),
	 * state changes are broadcast to ALL roots so every widget stays in sync.
	 * dispatchFilter is called only once (from the originating root) to avoid
	 * duplicate AJAX requests.
	 *
	 * @param {HTMLElement} root      .wcsf element that triggered the change.
	 * @param {Object}      newState  New filter state (replaces activeState).
	 * @param {Object}      [options]
	 * @param {boolean}     [options.dispatch=true]   Whether to dispatch filter.
	 * @param {number}      [options.paged=1]          Page number for dispatch.
	 * @param {boolean}     [options.debounce=false]   Whether to debounce dispatch.
	 * @param {boolean}     [options.syncInputs=true]  Whether to sync DOM inputs from state.
	 *                                                  Pass false when DOM is already correct
	 *                                                  (e.g. after a user-triggered change event).
	 */
	function setState( root, newState, options ) {
		options = options || {};

		var shouldDispatch = options.dispatch !== false;
		var paged          = options.paged || 1;
		var useDebounce    = options.debounce === true;
		var syncInputs     = options.syncInputs !== false;

		// 1. Update shared source of truth.
		activeState = newState;

		// 2. Broadcast to ALL registered roots (inputs + chips + open panels).
		allRoots.forEach( function ( r ) {
			// For the originating root, skip input sync if the DOM is already correct
			// (user just changed it). For other roots always sync inputs so they
			// mirror the state (e.g. horizontal filter reflects sidebar selection).
			var doSync = ( r === root ) ? syncInputs : true;

			if ( doSync ) {
				applyStateToInputs( r, newState );
			}

			updateActiveChips( r, newState );
			openFiltersWithActiveInputs( r );
		} );

		// 3. Dispatch filter request once (from the originating root).
		if ( shouldDispatch ) {
			if ( useDebounce ) {
				clearTimeout( debounceTimer );
				debounceTimer = setTimeout( function () {
					dispatchFilter( root, newState, paged );
				}, 300 );
			} else {
				dispatchFilter( root, newState, paged );
			}
		}
	}

	/**
	 * Wire reset buttons inside a root element.
	 *
	 * @param {HTMLElement} root  .wcsf element.
	 */
	function initResetButtons( root ) {
		root.querySelectorAll( '.wcsf__reset-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				// Reset all slider widgets to full range before clearing state.
				root.querySelectorAll( '.wcsf__slider-wrapper' ).forEach( function ( wrapper ) {
					var $sl     = $( wrapper ).find( '.wcsf__slider' );
					var fullMin = parseFloat( wrapper.getAttribute( 'data-min' ) ) || 0;
					var fullMax = parseFloat( wrapper.getAttribute( 'data-max' ) ) || 1000;

					if ( $sl.length && typeof $sl.slider === 'function' ) {
						$sl.slider( 'values', [ fullMin, fullMax ] );
						$( wrapper ).find( '.wcsf__slider-value--min' ).text( fullMin );
						$( wrapper ).find( '.wcsf__slider-value--max' ).text( fullMax );
					}
				} );

				setState( root, {}, { dispatch: true } );
			} );
		} );
	}

	/* =========================================================================
	   11. Pagination intercept (AJAX mode)
	   ========================================================================= */

	/**
	 * Intercept WooCommerce pagination links and do AJAX navigation.
	 * Re-called after each AJAX response to bind newly-injected pagination.
	 *
	 * @param {HTMLElement} root  .wcsf element.
	 */
	function initAjaxPagination( root ) {
		var mode = root.getAttribute( 'data-filter-mode' ) || cfg.filterMode || 'ajax';

		if ( mode !== 'ajax' ) {
			return;
		}

		$( '.woocommerce-pagination a, .wcsf-pagination a' ).off( 'click.wcsf' ).on( 'click.wcsf', function ( e ) {
			e.preventDefault();

			var href   = $( this ).attr( 'href' ) || '';
			var paged  = 1;
			var match  = href.match( /[?&]paged=(\d+)/ );

			if ( match ) {
				paged = parseInt( match[ 1 ], 10 );
			} else {
				// WC may use /page/N/ permalink format.
				match = href.match( /\/page\/(\d+)\// );
				if ( match ) {
					paged = parseInt( match[ 1 ], 10 );
				}
			}

			var state = collectFilterState( root );
			doAjaxFilter( root, state, paged );
		} );
	}

	/* =========================================================================
	   12. Filter input change listener
	   ========================================================================= */

	/**
	 * Listen for changes to all filter inputs and dispatch via setState.
	 *
	 * @param {HTMLElement} root  .wcsf element.
	 */
	function initFilterInputs( root ) {
		// Checkboxes, radios, and single dropdowns trigger on 'change'.
		root.addEventListener( 'change', function ( e ) {
			var el   = e.target;
			var name = el.name || '';

			// Match wcsf[...] or wcsf%5B...%5D (URL-encoded variant).
			if ( /^wcsf[\[%]/.test( name ) ) {
				var mode = root.getAttribute( 'data-filter-mode' ) || cfg.filterMode || 'ajax';

				if ( mode !== 'submit' ) {
					// DOM is already correct (user just changed it); skip input sync.
					var newState = collectFilterState( root );
					setState( root, newState, { dispatch: true, debounce: true, syncInputs: false } );
				}
			}
		} );

		// Radio toggle: clicking an already-checked radio deselects it.
		// The hidden <input type="radio"> is never the direct click target —
		// the user clicks <label> or the .wcsf__radio-custom span inside it.
		// We use mousedown (fires BEFORE the browser updates checked state) to
		// record which radio was already active, then in the label's click handler
		// we check against that snapshot. If the same radio was active before the
		// click we cancel the browser default (stops re-checking) and deselect.
		// For a different radio we do nothing — browser checks it, 'change' fires.
		var preClickChecked = null;

		root.addEventListener( 'mousedown', function ( e ) {
			var label = e.target.closest( 'label.wcsf__radio-label' );

			if ( ! label ) {
				preClickChecked = null;
				return;
			}

			var inputId = label.getAttribute( 'for' );
			var inp     = inputId ? document.getElementById( inputId ) : null;

			preClickChecked = ( inp && inp.type === 'radio' && inp.checked ) ? inp : null;
		} );

		root.addEventListener( 'click', function ( e ) {
			var label = e.target.closest( 'label.wcsf__radio-label' );

			if ( ! label ) {
				return;
			}

			var inputId = label.getAttribute( 'for' );
			var el      = inputId ? document.getElementById( inputId ) : null;

			if ( ! el || el.type !== 'radio' || ! /^wcsf[\[%]/.test( el.name || '' ) ) {
				return;
			}

			var mode = root.getAttribute( 'data-filter-mode' ) || cfg.filterMode || 'ajax';

			if ( mode === 'submit' ) {
				return;
			}

			if ( preClickChecked === el ) {
				// This radio was active before the click — deselect it.
				e.preventDefault();
				el.checked = false;
				preClickChecked = null;
				var newState = collectFilterState( root );
				setState( root, newState, { dispatch: true, debounce: true, syncInputs: false } );
			}
			// Different radio or nothing was checked before — browser handles
			// the check change and 'change' event fires normally.
		} );

		// Prevent native form submission in ajax/reload modes.
		var form = root.querySelector( '.wcsf__form' );

		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				var mode = root.getAttribute( 'data-filter-mode' ) || cfg.filterMode || 'ajax';

				if ( mode !== 'submit' ) {
					e.preventDefault();
					var newState = collectFilterState( root );
					setState( root, newState, { dispatch: true, syncInputs: false } );
				}
			} );
		}
	}

	/* =========================================================================
	   13. Helpers
	   ========================================================================= */

	/**
	 * Open sidebar filter panels that contain at least one active (checked/selected) input.
	 * Called after state is restored from URL or history, so active filters are visible.
	 *
	 * @param {HTMLElement} root  .wcsf element.
	 */
	function openFiltersWithActiveInputs( root ) {
		// Checked checkboxes and radios.
		root.querySelectorAll( 'input[name^="wcsf"]:checked' ).forEach( function ( el ) {
			var filterPanel = el.closest( '.wcsf__filter' );

			if ( ! filterPanel ) {
				return;
			}

			var btn = filterPanel.querySelector( '.wcsf__filter-toggle' );

			if ( btn ) {
				toggleSidebarFilter( btn, true );
			}
		} );

		// Selects with a non-empty selected value.
		root.querySelectorAll( 'select[name^="wcsf"]' ).forEach( function ( el ) {
			var hasValue = Array.from( el.options ).some( function ( o ) {
				return o.selected && o.value !== '';
			} );

			if ( ! hasValue ) {
				return;
			}

			var filterPanel = el.closest( '.wcsf__filter' );

			if ( ! filterPanel ) {
				return;
			}

			var btn = filterPanel.querySelector( '.wcsf__filter-toggle' );

			if ( btn ) {
				toggleSidebarFilter( btn, true );
			}
		} );

		// Hidden slider inputs with a non-empty value.
		root.querySelectorAll( '.wcsf__slider-input' ).forEach( function ( el ) {
			if ( el.value === '' ) {
				return;
			}

			var filterPanel = el.closest( '.wcsf__filter' );

			if ( ! filterPanel ) {
				return;
			}

			var btn = filterPanel.querySelector( '.wcsf__filter-toggle' );

			if ( btn ) {
				toggleSidebarFilter( btn, true );
			}
		} );
	}

	/**
	 * Find and cache the WooCommerce product loop wrapper.
	 * Searches common WC selectors.
	 *
	 * @return {jQuery|null}
	 */
	function getProductsWrapper() {
		if ( $productsWrap && $productsWrap.length ) {
			return $productsWrap;
		}

		var selectors = [
			'[data-wcsf-products]',
			'ul.products',
			'.woocommerce ul.products',
			'.woocommerce-page ul.products',
			'.products.columns-1, .products.columns-2, .products.columns-3, .products.columns-4',
		];

		for ( var i = 0; i < selectors.length; i++ ) {
			var $el = $( selectors[ i ] );

			if ( $el.length ) {
				$productsWrap = $el.first();
				return $productsWrap;
			}
		}

		return null;
	}

	/**
	 * Read the current WooCommerce orderby value from the page.
	 *
	 * @return {string}
	 */
	function getCurrentOrderby() {
		var $sel = $( 'select.orderby, form.woocommerce-ordering select' );

		return $sel.length ? $sel.val() || '' : '';
	}

	/* =========================================================================
	   Boot — wait for DOM ready
	   ========================================================================= */

	$( function () {
		var roots = document.querySelectorAll( '.wcsf' );

		if ( ! roots.length ) {
			return;
		}

		// Register all roots globally so setState() can broadcast to every widget.
		roots.forEach( function ( root ) {
			allRoots.push( root );
		} );

		roots.forEach( function ( root ) {
			var layout = root.getAttribute( 'data-layout' );

			if ( layout === 'sidebar' ) {
				initSidebar( root );
			} else if ( layout === 'horizontal' ) {
				initHorizontal( root );
			}

			initViewMore( root );
			initSliders( root );
			initFilterInputs( root );
			initResetButtons( root );
			initAjaxPagination( root );
		} );

		// Restore state from URL on page load (reload/back-forward).
		// Use the first root as the dispatcher; setState broadcasts to all.
		var primaryRoot = allRoots[ 0 ];
		readStateFromUrl( primaryRoot );

		// popstate is a global event — register once, not per root.
		initPopState( primaryRoot );
	} );

}( jQuery ) );
