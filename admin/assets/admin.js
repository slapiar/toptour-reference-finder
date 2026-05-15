/**
 * TOPTOUR Reference Finder Admin JavaScript
 *
 * @package Toptour_Ref
 * @version 0.1.0
 */

( function( $ ) {
	'use strict';

	// Plugin namespace.
	const ToptourRef = {
		/**
		 * Initialize plugin admin scripts.
		 */
		init: function() {
			this.ready();
		},

		/**
		 * Document ready handler.
		 */
		ready: function() {
			// Currently empty - placeholder for future functionality.
			// All admin interactions are handled server-side in MVP.
		}
	};

	// Initialize on document ready.
	$( document ).ready( function() {
		ToptourRef.init();
	} );

	// Export for testing and extension.
	window.ToptourRef = ToptourRef;

} )( jQuery );
