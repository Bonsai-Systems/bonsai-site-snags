/**
 * Site Snags — front-end toggle + click-to-pin QA layer.
 * Vanilla JS + jQuery for AJAX only, per Bonsai conventions. No build step.
 */
( function ( $ ) {
	'use strict';

	var snagMode = false;
	var $body = $( document.body );
	var $root = $( '#site-snags-root' );
	var $toggle = $( '#site-snags-toggle' );
	var $pinLayer;
	var activePopover = null;

	/**
	 * Build a reasonably stable CSS selector path for an element by walking
	 * up to the nearest ancestor with an ID, or falling back to nth-child
	 * chains. Doesn't need to be pretty, just needs to re-find the element.
	 */
	function getSelectorPath( el ) {
		if ( ! ( el instanceof Element ) ) {
			return '';
		}

		var path = [];
		var current = el;

		while ( current && current.nodeType === Node.ELEMENT_NODE ) {
			if ( current.id ) {
				path.unshift( '#' + current.id );
				break;
			}

			var selector = current.tagName.toLowerCase();
			var siblingIndex = 1;
			var sibling = current.previousElementSibling;

			while ( sibling ) {
				if ( sibling.tagName === current.tagName ) {
					siblingIndex++;
				}
				sibling = sibling.previousElementSibling;
			}

			selector += ':nth-of-type(' + siblingIndex + ')';
			path.unshift( selector );

			current = current.parentElement;

			// Stop at body — no need to path all the way to <html>.
			if ( current === document.body ) {
				path.unshift( 'body' );
				break;
			}
		}

		return path.join( ' > ' );
	}

	/**
	 * Resolve a stored selector back to a live element, if it still exists.
	 */
	function resolveSelector( selector ) {
		if ( ! selector ) {
			return null;
		}
		try {
			return document.querySelector( selector );
		} catch ( e ) {
			return null;
		}
	}

	/**
	 * Convert an element + click coordinates into percentage offsets within
	 * that element's bounding box, so pins survive reflows/responsive shifts.
	 */
	function getPercentOffsets( el, clientX, clientY ) {
		var rect = el.getBoundingClientRect();
		var x = rect.width ? ( ( clientX - rect.left ) / rect.width ) * 100 : 50;
		var y = rect.height ? ( ( clientY - rect.top ) / rect.height ) * 100 : 50;
		return {
			x: Math.max( 0, Math.min( 100, x ) ),
			y: Math.max( 0, Math.min( 100, y ) ),
		};
	}

	/**
	 * Turn a stored element + percentage offset back into page coordinates.
	 */
	function getPixelPosition( el, offsetX, offsetY ) {
		var rect = el.getBoundingClientRect();
		return {
			left: rect.left + window.scrollX + ( rect.width * ( offsetX / 100 ) ),
			top: rect.top + window.scrollY + ( rect.height * ( offsetY / 100 ) ),
		};
	}

	function ensurePinLayer() {
		if ( ! $pinLayer ) {
			$pinLayer = $( '<div id="site-snags-pins"></div>' ).appendTo( $body );
		}
		return $pinLayer;
	}

	function closeActivePopover() {
		if ( activePopover ) {
			activePopover.remove();
			activePopover = null;
		}
	}

	/**
	 * Render a single pin marker on the page at the given element/offset.
	 */
	function renderPin( snag ) {
		var el = resolveSelector( snag.selector );
		var pos;

		if ( el ) {
			pos = getPixelPosition( el, snag.offset_x, snag.offset_y );
		} else {
			// Element no longer exists — skip rendering, it still lives in
			// the admin list by URL so nothing is lost.
			return;
		}

		var status = snag.status || 'open';

		var $pin = $( '<button type="button" class="site-snags-pin site-snags-pin--' + status + '"></button>' )
			.attr( 'data-id', snag.id )
			.css( { left: pos.left + 'px', top: pos.top + 'px' } )
			.attr( 'title', snag.note )
			.attr( 'aria-label', snag.note );

		$pin.on( 'click', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			openDetailPopover( $pin, snag, el );
		} );

		ensurePinLayer().append( $pin );
	}

	function reflowPins() {
		ensurePinLayer().empty();
		loadSnagsForPage();
	}

	/**
	 * Popover shown when creating a brand new snag from a click.
	 */
	function openCreatePopover( x, y, selector, offsetX, offsetY ) {
		closeActivePopover();

		var $popover = $(
			'<div class="site-snags-popover">' +
				'<textarea class="site-snags-popover__input" placeholder="' + SiteSnags.i18n.placeholder + '"></textarea>' +
				'<div class="site-snags-popover__actions">' +
					'<button type="button" class="site-snags-btn site-snags-btn--cancel">' + SiteSnags.i18n.cancel + '</button>' +
					'<button type="button" class="site-snags-btn site-snags-btn--save">' + SiteSnags.i18n.save + '</button>' +
				'</div>' +
			'</div>'
		);

		$popover.css( { left: x + 'px', top: y + 'px' } );
		$body.append( $popover );
		activePopover = $popover;

		var $textarea = $popover.find( '.site-snags-popover__input' );
		$textarea.trigger( 'focus' );

		$popover.find( '.site-snags-btn--cancel' ).on( 'click', closeActivePopover );

		$popover.find( '.site-snags-btn--save' ).on( 'click', function () {
			var note = $.trim( $textarea.val() );
			if ( ! note ) {
				$textarea.trigger( 'focus' );
				return;
			}

			$.post( SiteSnags.ajaxUrl, {
				action: 'site_snags_create',
				nonce: SiteSnags.nonce,
				note: note,
				url: SiteSnags.pageUrl,
				page_title: SiteSnags.pageTitle,
				selector: selector,
				offset_x: offsetX,
				offset_y: offsetY,
			} ).done( function ( response ) {
				closeActivePopover();
				if ( response && response.success ) {
					renderPin( response.data );
				}
			} );
		} );
	}

	/**
	 * Popover shown when clicking an existing pin — view/edit/resolve/delete.
	 */
	function openDetailPopover( $pin, snag, el ) {
		closeActivePopover();

		var pos = getPixelPosition( el, snag.offset_x, snag.offset_y );
		var isDone = snag.status === 'done';

		var $popover = $(
			'<div class="site-snags-popover site-snags-popover--detail">' +
				'<div class="site-snags-popover__meta">' + snag.author + ' — ' + snag.created_at + '</div>' +
				'<textarea class="site-snags-popover__input">' + $( '<div>' ).text( snag.note ).html() + '</textarea>' +
				'<div class="site-snags-popover__actions">' +
					'<button type="button" class="site-snags-btn site-snags-btn--delete">' + SiteSnags.i18n['delete'] + '</button>' +
					'<button type="button" class="site-snags-btn site-snags-btn--status">' + ( isDone ? SiteSnags.i18n.reopen : SiteSnags.i18n.markDone ) + '</button>' +
					'<button type="button" class="site-snags-btn site-snags-btn--save">' + SiteSnags.i18n.save + '</button>' +
				'</div>' +
			'</div>'
		);

		$popover.css( { left: pos.left + 'px', top: pos.top + 'px' } );
		$body.append( $popover );
		activePopover = $popover;

		var $textarea = $popover.find( '.site-snags-popover__input' );

		$popover.find( '.site-snags-btn--save' ).on( 'click', function () {
			var note = $.trim( $textarea.val() );
			if ( ! note ) {
				return;
			}
			$.post( SiteSnags.ajaxUrl, {
				action: 'site_snags_update_note',
				nonce: SiteSnags.nonce,
				id: snag.id,
				note: note,
			} ).done( function ( response ) {
				if ( response && response.success ) {
					$pin.attr( 'title', note ).attr( 'aria-label', note );
					closeActivePopover();
				}
			} );
		} );

		$popover.find( '.site-snags-btn--status' ).on( 'click', function () {
			var newStatus = isDone ? 'open' : 'done';
			$.post( SiteSnags.ajaxUrl, {
				action: 'site_snags_update_status',
				nonce: SiteSnags.nonce,
				id: snag.id,
				status: newStatus,
			} ).done( function ( response ) {
				if ( response && response.success ) {
					$pin.removeClass( 'site-snags-pin--open site-snags-pin--done' ).addClass( 'site-snags-pin--' + newStatus );
					closeActivePopover();
				}
			} );
		} );

		$popover.find( '.site-snags-btn--delete' ).on( 'click', function () {
			$.post( SiteSnags.ajaxUrl, {
				action: 'site_snags_delete',
				nonce: SiteSnags.nonce,
				id: snag.id,
			} ).done( function ( response ) {
				if ( response && response.success ) {
					$pin.remove();
					closeActivePopover();
				}
			} );
		} );
	}

	/**
	 * Load existing snags for the current URL and render their pins.
	 */
	function loadSnagsForPage() {
		$.post( SiteSnags.ajaxUrl, {
			action: 'site_snags_fetch_for_page',
			nonce: SiteSnags.nonce,
			url: SiteSnags.pageUrl,
		} ).done( function ( response ) {
			if ( response && response.success && response.data.snags ) {
				response.data.snags.forEach( renderPin );
			}
		} );
	}

	/**
	 * Handles a click anywhere on the page while snag mode is active.
	 */
	function handleSnagClick( e ) {
		// Ignore clicks on our own UI (toggle, pins, popovers).
		if ( $( e.target ).closest( '#site-snags-root, #site-snags-pins, .site-snags-popover' ).length ) {
			return;
		}

		e.preventDefault();
		e.stopPropagation();

		var selector = getSelectorPath( e.target );
		var offsets = getPercentOffsets( e.target, e.clientX, e.clientY );

		openCreatePopover(
			e.pageX,
			e.pageY,
			selector,
			offsets.x,
			offsets.y
		);
	}

	function enableSnagMode() {
		snagMode = true;
		$body.addClass( 'site-snags-mode-active' );
		$toggle.attr( 'aria-pressed', 'true' ).addClass( 'is-active' );
		$toggle.find( '.site-snags-toggle__label' ).text( SiteSnags.i18n.toggleOn );
		document.addEventListener( 'click', handleSnagClick, true );
	}

	function disableSnagMode() {
		snagMode = false;
		$body.removeClass( 'site-snags-mode-active' );
		$toggle.attr( 'aria-pressed', 'false' ).removeClass( 'is-active' );
		$toggle.find( '.site-snags-toggle__label' ).text( SiteSnags.i18n.toggleOff );
		document.removeEventListener( 'click', handleSnagClick, true );
		closeActivePopover();
	}

	$( function () {
		if ( typeof SiteSnags === 'undefined' ) {
			return;
		}

		$toggle.find( '.site-snags-toggle__label' ).text( SiteSnags.i18n.toggleOff );

		$toggle.on( 'click', function () {
			if ( snagMode ) {
				disableSnagMode();
			} else {
				enableSnagMode();
			}
		} );

		// Pins always render regardless of mode, so admins can see open
		// snags at a glance even before switching into snag mode.
		loadSnagsForPage();

		$( window ).on( 'resize', debounce( reflowPins, 250 ) );
	} );

	function debounce( fn, wait ) {
		var t;
		return function () {
			clearTimeout( t );
			var args = arguments;
			t = setTimeout( function () {
				fn.apply( null, args );
			}, wait );
		};
	}
} )( jQuery );
