( function () {
	'use strict';

	function insertNotice( message, type ) {
		var wrap = document.querySelector( '.plugitify-plugins' );
		var anchor = wrap && wrap.querySelector( '.wp-header-end' );

		if ( ! wrap || ! anchor ) {
			return;
		}

		var notice = document.createElement( 'div' );
		notice.className = 'notice notice-' + type + ' is-dismissible plugitify-notice';

		var text = document.createElement( 'p' );
		text.textContent = message;
		notice.appendChild( text );

		anchor.insertAdjacentElement( 'afterend', notice );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.addEventListener( 'click', function ( event ) {
			var deleteButton = event.target.closest( '[data-plugitify-delete]' );

			if ( ! deleteButton ) {
				return;
			}

			var slug = deleteButton.getAttribute( 'data-slug' );
			var name = deleteButton.getAttribute( 'data-name' );
			var confirmMessage = plugitifyAdmin.strings.confirmDelete.replace( '%s', name );

			if ( ! window.confirm( confirmMessage ) ) {
				return;
			}

			deleteButton.disabled = true;

			var deleteBody = new URLSearchParams();
			deleteBody.set( 'action', 'plugitify_delete_plugin' );
			deleteBody.set( 'nonce', plugitifyAdmin.deleteNonce );
			deleteBody.set( 'slug', slug );

			fetch( plugitifyAdmin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: deleteBody.toString(),
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( response ) {
					if ( response && response.success ) {
						window.location.reload();
						return;
					}

					var message = response && response.data && response.data.message
						? response.data.message
						: plugitifyAdmin.strings.errorGeneric;

					insertNotice( message, 'error' );
					deleteButton.disabled = false;
				} )
				.catch( function () {
					insertNotice( plugitifyAdmin.strings.errorGeneric, 'error' );
					deleteButton.disabled = false;
				} );
		} );

		document.addEventListener( 'click', function ( event ) {
			var toggle = event.target.closest( '[data-plugitify-toggle]' );

			if ( ! toggle ) {
				return;
			}

			var slug = toggle.getAttribute( 'data-slug' );

			toggle.disabled = true;

			var toggleBody = new URLSearchParams();
			toggleBody.set( 'action', 'plugitify_toggle_plugin' );
			toggleBody.set( 'nonce', plugitifyAdmin.toggleNonce );
			toggleBody.set( 'slug', slug );

			fetch( plugitifyAdmin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: toggleBody.toString(),
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( response ) {
					if ( response && response.success ) {
						window.location.reload();
						return;
					}

					toggle.disabled = false;

					var message = response && response.data && response.data.message
						? response.data.message
						: plugitifyAdmin.strings.errorGeneric;

					insertNotice( message, 'error' );
				} )
				.catch( function () {
					toggle.disabled = false;
					insertNotice( plugitifyAdmin.strings.errorGeneric, 'error' );
				} );
		} );

		var searchInput = document.getElementById( 'plugitify-search' );
		var rows        = document.querySelectorAll( '[data-plugitify-row]' );
		var noResults   = document.getElementById( 'plugitify-no-results' );

		if ( searchInput ) {
			searchInput.addEventListener( 'input', function () {
				var query = searchInput.value.trim().toLowerCase();
				var visibleCount = 0;

				Array.prototype.forEach.call( rows, function ( row ) {
					var matches = '' === query || ( row.getAttribute( 'data-search' ) || '' ).indexOf( query ) !== -1;
					row.hidden = ! matches;

					if ( matches ) {
						visibleCount++;
					}
				} );

				if ( noResults ) {
					noResults.hidden = 0 !== visibleCount;
				}
			} );
		}

		var selectAll      = document.getElementById( 'plugitify-select-all' );
		var bulkButtons    = document.querySelectorAll( '[data-plugitify-bulk]' );
		var rowCheckboxes  = document.querySelectorAll( '[data-plugitify-row-checkbox]' );

		function getSelectedSlugs() {
			return Array.prototype.filter.call( rowCheckboxes, function ( checkbox ) {
				return checkbox.checked;
			} ).map( function ( checkbox ) {
				return checkbox.getAttribute( 'data-slug' );
			} );
		}

		function updateBulkButtons() {
			var hasSelection = getSelectedSlugs().length > 0;

			Array.prototype.forEach.call( bulkButtons, function ( button ) {
				button.disabled = ! hasSelection;
			} );
		}

		Array.prototype.forEach.call( rowCheckboxes, function ( checkbox ) {
			checkbox.addEventListener( 'change', function () {
				if ( selectAll && ! checkbox.checked ) {
					selectAll.checked = false;
				}

				updateBulkButtons();
			} );
		} );

		if ( selectAll ) {
			selectAll.addEventListener( 'change', function () {
				Array.prototype.forEach.call( rowCheckboxes, function ( checkbox ) {
					checkbox.checked = selectAll.checked;
				} );

				updateBulkButtons();
			} );
		}

		Array.prototype.forEach.call( bulkButtons, function ( button ) {
			button.addEventListener( 'click', function () {
				var bulkAction = button.getAttribute( 'data-plugitify-bulk' );
				var slugs = getSelectedSlugs();

				if ( 0 === slugs.length ) {
					return;
				}

				if ( 'delete' === bulkAction && ! window.confirm( plugitifyAdmin.strings.confirmBulkDelete ) ) {
					return;
				}

				Array.prototype.forEach.call( bulkButtons, function ( btn ) {
					btn.disabled = true;
				} );

				var bulkBody = new URLSearchParams();
				bulkBody.set( 'action', 'plugitify_bulk_action' );
				bulkBody.set( 'nonce', plugitifyAdmin.bulkNonce );
				bulkBody.set( 'bulk_action', bulkAction );

				slugs.forEach( function ( slug ) {
					bulkBody.append( 'slugs[]', slug );
				} );

				fetch( plugitifyAdmin.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: bulkBody.toString(),
				} )
					.then( function ( response ) {
						return response.json();
					} )
					.then( function ( response ) {
						if ( response && response.success ) {
							window.location.reload();
							return;
						}

						updateBulkButtons();

						var message = response && response.data && response.data.message
							? response.data.message
							: plugitifyAdmin.strings.errorGeneric;

						insertNotice( message, 'error' );
					} )
					.catch( function () {
						updateBulkButtons();
						insertNotice( plugitifyAdmin.strings.errorGeneric, 'error' );
					} );
			} );
		} );

		var openButton = document.getElementById( 'plugitify-open-create-modal' );
		var modal      = document.getElementById( 'plugitify-create-modal' );
		var form       = document.getElementById( 'plugitify-create-form' );

		if ( ! openButton || ! modal || ! form ) {
			return;
		}

		var nameField        = document.getElementById( 'plugitify-plugin-name' );
		var slugField        = document.getElementById( 'plugitify-plugin-slug' );
		var descriptionField = document.getElementById( 'plugitify-plugin-description' );
		var errorBox         = document.getElementById( 'plugitify-create-error' );
		var submitButton = form.querySelector( 'button[type="submit"]' );
		var slugTouched = false;

		function slugify( value ) {
			return value
				.toString()
				.trim()
				.toLowerCase()
				.replace( /[^a-z0-9]+/g, '-' )
				.replace( /^-+|-+$/g, '' );
		}

		function showError( message ) {
			errorBox.textContent = message;
			errorBox.hidden = false;
		}

		function hideError() {
			errorBox.hidden = true;
			errorBox.textContent = '';
		}

		function openModal() {
			modal.hidden = false;
			document.body.classList.add( 'plugitify-modal-open' );
			nameField.focus();
		}

		function closeModal() {
			modal.hidden = true;
			document.body.classList.remove( 'plugitify-modal-open' );
			form.reset();
			slugTouched = false;
			hideError();
			submitButton.disabled = false;
		}

		openButton.addEventListener( 'click', openModal );

		Array.prototype.forEach.call( modal.querySelectorAll( '[data-plugitify-close]' ), function ( el ) {
			el.addEventListener( 'click', closeModal );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && ! modal.hidden ) {
				closeModal();
			}
		} );

		nameField.addEventListener( 'input', function () {
			if ( ! slugTouched ) {
				slugField.value = slugify( nameField.value );
			}
		} );

		slugField.addEventListener( 'input', function () {
			slugTouched = true;
			slugField.value = slugify( slugField.value );
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			hideError();

			var name = nameField.value.trim();
			var slug = slugField.value.trim();
			var description = descriptionField.value.trim();
			var slugPattern = /^[a-z0-9]+(-[a-z0-9]+)*$/;

			if ( '' === name ) {
				showError( plugitifyAdmin.strings.errorName );
				return;
			}

			if ( ! slugPattern.test( slug ) ) {
				showError( plugitifyAdmin.strings.errorSlug );
				return;
			}

			if ( '' === description ) {
				showError( plugitifyAdmin.strings.errorDescription );
				return;
			}

			submitButton.disabled = true;

			var body = new URLSearchParams();
			body.set( 'action', 'plugitify_create_plugin' );
			body.set( 'nonce', plugitifyAdmin.nonce );
			body.set( 'name', name );
			body.set( 'slug', slug );
			body.set( 'description', description );

			fetch( plugitifyAdmin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( response ) {
					if ( response && response.success ) {
						window.location.reload();
						return;
					}

					var message = response && response.data && response.data.message
						? response.data.message
						: plugitifyAdmin.strings.errorGeneric;

					showError( message );
					submitButton.disabled = false;
				} )
				.catch( function () {
					showError( plugitifyAdmin.strings.errorGeneric );
					submitButton.disabled = false;
				} );
		} );
	} );
}() );
