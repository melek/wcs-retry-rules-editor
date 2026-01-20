/**
 * WCS Retry Rules Editor - Admin JavaScript
 *
 * Vanilla JavaScript implementation for the retry rules visual editor.
 *
 * @package WCS_Retry_Rules_Editor
 */

( function() {
	'use strict';

	// State
	let rules = [];
	let config = {};
	let isDefault = true;
	let hasChanges = false;
	let isSaving = false;
	let previewModal = {
		open: false,
		loading: false,
		error: '',
		html: '',
		subject: '',
		heading: '',
		ruleIndex: null,
		recipient: '',
	};

	// DOM Elements
	let app;

	/**
	 * Initialize the application.
	 */
	function init() {
		app = document.getElementById( 'wcs-rre-app' );
		if ( ! app ) {
			return;
		}

		// Set up beforeunload warning
		window.addEventListener( 'beforeunload', handleBeforeUnload );
		document.addEventListener( 'keydown', handleModalEscape );

		// Load initial data
		loadData();
	}

	/**
	 * Handle beforeunload event to warn about unsaved changes.
	 *
	 * @param {Event} e The beforeunload event.
	 */
	function handleBeforeUnload( e ) {
		if ( hasChanges ) {
			e.preventDefault();
			e.returnValue = wcsRreData.strings.unsavedChanges;
			return e.returnValue;
		}
	}

	/**
	 * Load rules and config from the API.
	 */
	async function loadData() {
		try {
			const [ rulesResponse, configResponse ] = await Promise.all( [
				apiFetch( '/rules' ),
				apiFetch( '/config' ),
			] );

			rules = rulesResponse.rules || [];
			isDefault = rulesResponse.is_default || false;
			config = configResponse;

			render();
		} catch ( error ) {
			showNotice( wcsRreData.strings.loadError + ' ' + error.message, 'error' );
			app.innerHTML = '<div class="notice notice-error"><p>' + wcsRreData.strings.loadError + ' ' + escapeHtml( error.message ) + '</p></div>';
		}
	}

	/**
	 * Make an API request.
	 *
	 * @param {string} endpoint The API endpoint.
	 * @param {Object} options  Fetch options.
	 * @return {Promise} The fetch promise.
	 */
	async function apiFetch( endpoint, options = {} ) {
		const url = '/wp-json/' + wcsRreData.apiNamespace + endpoint;

		const fetchOptions = {
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': wcsRreData.nonce,
			},
			credentials: 'same-origin',
			...options,
		};

		const response = await fetch( url, fetchOptions );
		const data = await response.json();

		if ( ! response.ok ) {
			throw new Error( data.message || 'API request failed' );
		}

		return data;
	}

	/**
	 * Render the entire application.
	 */
	function render() {
		app.innerHTML = `
			<div class="wcs-rre-header">
				<div class="wcs-rre-status ${isDefault ? 'is-default' : 'is-custom'}">
					${isDefault ? wcsRreData.strings.usingDefaults : wcsRreData.strings.usingCustom}
					${hasChanges ? ' <span class="wcs-rre-unsaved">(unsaved changes)</span>' : ''}
				</div>
				<div class="wcs-rre-actions">
					<button type="button" class="button" id="wcs-rre-reset" ${isSaving ? 'disabled' : ''}>
						${wcsRreData.strings.reset}
					</button>
					<button type="button" class="button button-primary" id="wcs-rre-save" ${isSaving ? 'disabled' : ''}>
						${isSaving ? wcsRreData.strings.saving : wcsRreData.strings.save}
					</button>
				</div>
			</div>

			<div class="wcs-rre-content">
				<div class="wcs-rre-rules">
					${renderRulesList()}
					<button type="button" class="button wcs-rre-add-rule" id="wcs-rre-add">
						+ ${wcsRreData.strings.addRule}
					</button>
				</div>

				<div class="wcs-rre-side">
					<div class="wcs-rre-timeline">
						<h3>${wcsRreData.strings.timeline}</h3>
						${renderTimeline()}
					</div>
				</div>
			</div>
			${renderPreviewModal()}
		`;

		attachEventListeners();
	}

	/**
	 * Render the list of rules.
	 *
	 * @return {string} HTML string.
	 */
	function renderRulesList() {
		if ( rules.length === 0 ) {
			return '<div class="notice notice-info"><p>No rules configured. Click "Add Rule" to create one.</p></div>';
		}

		return rules.map( ( rule, index ) => renderRuleCard( rule, index ) ).join( '' );
	}

	/**
	 * Render a single rule card.
	 *
	 * @param {Object} rule  The rule object.
	 * @param {number} index The rule index.
	 * @return {string} HTML string.
	 */
	function renderRuleCard( rule, index ) {
		const interval = formatInterval( rule.retry_after_interval );
		const cumulative = formatInterval( getCumulativeTime( index ) );

		return `
			<div class="wcs-rre-rule-card" data-index="${index}">
				<div class="wcs-rre-rule-header">
					<span class="wcs-rre-rule-number">${wcsRreData.strings.rule} ${index + 1}</span>
					<div class="wcs-rre-rule-actions">
						${index > 0 ? `<button type="button" class="button-link wcs-rre-move-up" data-index="${index}" title="${wcsRreData.strings.moveUp}">&uarr;</button>` : ''}
						${index < rules.length - 1 ? `<button type="button" class="button-link wcs-rre-move-down" data-index="${index}" title="${wcsRreData.strings.moveDown}">&darr;</button>` : ''}
						<button type="button" class="button-link wcs-rre-delete" data-index="${index}">${wcsRreData.strings.deleteRule}</button>
					</div>
				</div>

				<div class="wcs-rre-rule-body">
					<div class="wcs-rre-field">
						<label>${wcsRreData.strings.retryAfter}</label>
						<div class="wcs-rre-interval-input">
							<input type="number" class="wcs-rre-interval-value" data-index="${index}"
								value="${getIntervalValue( rule.retry_after_interval )}" min="5" step="1">
							<select class="wcs-rre-interval-unit" data-index="${index}">
								<option value="60" ${getIntervalUnit( rule.retry_after_interval ) === 60 ? 'selected' : ''}>${wcsRreData.strings.minutes}</option>
								<option value="3600" ${getIntervalUnit( rule.retry_after_interval ) === 3600 ? 'selected' : ''}>${wcsRreData.strings.hours}</option>
								<option value="86400" ${getIntervalUnit( rule.retry_after_interval ) === 86400 ? 'selected' : ''}>${wcsRreData.strings.days}</option>
							</select>
						</div>
						<span class="wcs-rre-cumulative">${wcsRreData.strings.cumulativeTime} ${cumulative}</span>
					</div>

					<div class="wcs-rre-field">
						<label>${wcsRreData.strings.customerEmail}</label>
						<select class="wcs-rre-customer-email" data-index="${index}">
							${renderEmailOptions( 'customer', rule.email_template_customer )}
						</select>
					</div>

					<div class="wcs-rre-field">
						<label>${wcsRreData.strings.adminEmail}</label>
						<select class="wcs-rre-admin-email" data-index="${index}">
							${renderEmailOptions( 'admin', rule.email_template_admin )}
						</select>
					</div>

					<div class="wcs-rre-field">
						<label>${wcsRreData.strings.orderStatus}</label>
						<select class="wcs-rre-order-status" data-index="${index}">
							${renderStatusOptions( config.order_statuses, rule.status_to_apply_to_order )}
						</select>
					</div>

					<div class="wcs-rre-field">
						<label>${wcsRreData.strings.subscriptionStatus}</label>
						<select class="wcs-rre-subscription-status" data-index="${index}">
							${renderStatusOptions( config.subscription_statuses, rule.status_to_apply_to_subscription )}
						</select>
					</div>
				</div>

				${renderEmailOverrides( rule, index )}
			</div>
		`;
	}

	/**
	 * Render email template options.
	 *
	 * @param {string} type     Email type (customer or admin).
	 * @param {string} selected Currently selected value.
	 * @return {string} HTML options string.
	 */
	function renderEmailOptions( type, selected ) {
		const options = config.email_templates[ type ] || {};
		return Object.entries( options ).map( ( [ value, label ] ) =>
			`<option value="${escapeHtml( value )}" ${value === selected ? 'selected' : ''}>${escapeHtml( label )}</option>`
		).join( '' );
	}

	/**
	 * Render status options.
	 *
	 * @param {Object} statuses Available statuses.
	 * @param {string} selected Currently selected value.
	 * @return {string} HTML options string.
	 */
	function renderStatusOptions( statuses, selected ) {
		return Object.entries( statuses || {} ).map( ( [ value, label ] ) =>
			`<option value="${escapeHtml( value )}" ${value === selected ? 'selected' : ''}>${escapeHtml( label )}</option>`
		).join( '' );
	}

	/**
	 * Render the timeline preview.
	 *
	 * @return {string} HTML string.
	 */
	function renderTimeline() {
		if ( rules.length === 0 ) {
			return '<div class="wcs-rre-timeline-empty">Add rules to see the timeline preview.</div>';
		}

		let html = '<div class="wcs-rre-timeline-list">';
		html += `<div class="wcs-rre-timeline-item wcs-rre-timeline-start">
			<span class="wcs-rre-timeline-marker"></span>
			<span class="wcs-rre-timeline-label">${wcsRreData.strings.paymentFails}</span>
		</div>`;

		rules.forEach( ( rule, index ) => {
			const cumulative = formatInterval( getCumulativeTime( index ) );
			const hasCustomerEmail = rule.email_template_customer !== '';
			const hasAdminEmail = rule.email_template_admin !== '';

			let emailInfo = '';
			if ( hasCustomerEmail || hasAdminEmail ) {
				const emails = [];
				if ( hasCustomerEmail ) emails.push( 'Customer' );
				if ( hasAdminEmail ) emails.push( 'Admin' );
				emailInfo = ` (${emails.join( ' + ' )} notified)`;
			}

			html += `<div class="wcs-rre-timeline-item">
				<span class="wcs-rre-timeline-marker"></span>
				<span class="wcs-rre-timeline-time">+${cumulative}</span>
				<span class="wcs-rre-timeline-label">${wcsRreData.strings.retryAttempt} ${index + 1}${emailInfo}</span>
			</div>`;
		} );

		html += `<div class="wcs-rre-timeline-item wcs-rre-timeline-end">
			<span class="wcs-rre-timeline-marker"></span>
			<span class="wcs-rre-timeline-label">${wcsRreData.strings.afterAllRetries}</span>
		</div>`;

		html += '</div>';
		return html;
	}

	/**
	 * Render email override fields.
	 *
	 * @param {Object} rule  The rule object.
	 * @param {number} index Rule index.
	 * @return {string} HTML string.
	 */
	function renderEmailOverrides( rule, index ) {
		const customerGroup = renderEmailOverrideGroup( 'customer', rule, index );
		const adminGroup = renderEmailOverrideGroup( 'admin', rule, index );

		if ( ! customerGroup && ! adminGroup ) {
			return '';
		}

		return `
			<div class="wcs-rre-email-overrides">
				<h4>${wcsRreData.strings.emailOverridesTitle}</h4>
				<p class="description">${wcsRreData.strings.emailOverridesDesc}</p>
				${customerGroup}
				${adminGroup}
			</div>
		`;
	}

	/**
	 * Render email override group.
	 *
	 * @param {string} recipient Recipient type.
	 * @param {Object} rule      Rule data.
	 * @param {number} index     Rule index.
	 * @return {string} HTML string.
	 */
	function renderEmailOverrideGroup( recipient, rule, index ) {
		const isCustomer = recipient === 'customer';
		const label = isCustomer ? wcsRreData.strings.emailCustomerLabel : wcsRreData.strings.emailAdminLabel;
		const disabled = isCustomer ? ! rule.email_template_customer : ! rule.email_template_admin;
		if ( disabled ) {
			return '';
		}
		const disabledAttr = disabled ? 'disabled' : '';
		const subjectKey = `email_subject_${recipient}`;
		const headingKey = `email_heading_${recipient}`;
		const additionalKey = `email_additional_content_${recipient}`;
		const overrideKey = `email_override_${recipient}`;
		const overrideEnabled = Boolean( rule[ overrideKey ] );
		const defaults = config.email_preview && config.email_preview[ recipient ]
			? config.email_preview[ recipient ]
			: { default_subject: '', default_heading: '', default_additional: '' };
		const placeholders = wcsRreData.emailPlaceholders && wcsRreData.emailPlaceholders[ recipient ]
			? wcsRreData.emailPlaceholders[ recipient ]
			: [];
		const placeholderTip = placeholders.length
			? `<span class="woocommerce-help-tip" data-tip="${escapeAttribute( `${wcsRreData.strings.emailPlaceholders} ${placeholders.join( ', ' )}` )}"></span>`
			: '';

		return `
			<div class="wcs-rre-email-group ${disabled ? 'is-disabled' : ''}">
				<div class="wcs-rre-email-group-header">
					<span>${label} ${placeholderTip}</span>
					<button type="button" class="button wcs-rre-email-preview" data-index="${index}" data-recipient="${recipient}" ${disabledAttr}>
						${wcsRreData.strings.emailPreview}
					</button>
				</div>
				<label class="wcs-rre-email-toggle">
					<input type="checkbox" class="wcs-rre-email-override-toggle" data-index="${index}" data-field="${overrideKey}" ${overrideEnabled ? 'checked' : ''}>
					${wcsRreData.strings.emailOverrideToggle}
				</label>
				${overrideEnabled ? `
					<div class="wcs-rre-email-fields">
						<div class="wcs-rre-email-field">
							<label>${wcsRreData.strings.emailSubject}</label>
							<input type="text" class="wcs-rre-email-override-input" data-index="${index}" data-field="${subjectKey}"
								value="${escapeHtml( rule[ subjectKey ] || '' )}" placeholder="${escapeHtml( defaults.default_subject || '' )}">
						</div>
						<div class="wcs-rre-email-field">
							<label>${wcsRreData.strings.emailHeading}</label>
							<input type="text" class="wcs-rre-email-override-input" data-index="${index}" data-field="${headingKey}"
								value="${escapeHtml( rule[ headingKey ] || '' )}" placeholder="${escapeHtml( defaults.default_heading || '' )}">
						</div>
						<div class="wcs-rre-email-field wcs-rre-email-field--full">
							<label>${wcsRreData.strings.emailAdditional}</label>
							<textarea class="wcs-rre-email-override-input" data-index="${index}" data-field="${additionalKey}" rows="3"
								placeholder="${escapeHtml( defaults.default_additional || '' )}">${escapeHtml( rule[ additionalKey ] || '' )}</textarea>
						</div>
					</div>
				` : ''}
			</div>
		`;
	}

	/**
	 * Render preview modal.
	 *
	 * @return {string} HTML string.
	 */
	function renderPreviewModal() {
		if ( ! previewModal.open ) {
			return '';
		}

		const body = previewModal.loading
			? `<div class="wcs-rre-modal-loading">${wcsRreData.strings.emailPreviewLoad}</div>`
			: previewModal.error
				? `<div class="wcs-rre-modal-error">${wcsRreData.strings.emailPreviewError} ${escapeHtml( previewModal.error )}</div>`
				: `
					<div class="wcs-rre-modal-meta">
						<div><strong>${wcsRreData.strings.emailPreviewSubject}:</strong> ${escapeHtml( previewModal.subject || '' )}</div>
						<div><strong>${wcsRreData.strings.emailPreviewHeading}:</strong> ${escapeHtml( previewModal.heading || '' )}</div>
					</div>
					<div class="wcs-rre-modal-frame">
						<iframe title="${wcsRreData.strings.emailPreviewTitle}" srcdoc="${escapeAttribute( previewModal.html || '' )}"></iframe>
					</div>
				`;

		return `
			<div class="wcs-rre-modal-backdrop" data-modal-backdrop="true">
				<div class="wcs-rre-modal" role="dialog" aria-modal="true" aria-label="${wcsRreData.strings.emailPreviewTitle}">
					<div class="wcs-rre-modal-header">
						<h3>${wcsRreData.strings.emailPreviewTitle}</h3>
						<button type="button" class="button wcs-rre-modal-close" data-modal-close="true">
							${wcsRreData.strings.emailPreviewClose}
						</button>
					</div>
					${body}
				</div>
			</div>
		`;
	}

	/**
	 * Get cumulative time up to and including a rule index.
	 *
	 * @param {number} index Rule index.
	 * @return {number} Total seconds.
	 */
	function getCumulativeTime( index ) {
		let total = 0;
		for ( let i = 0; i <= index; i++ ) {
			total += rules[ i ].retry_after_interval;
		}
		return total;
	}

	/**
	 * Format interval in seconds to human-readable string.
	 *
	 * @param {number} seconds Interval in seconds.
	 * @return {string} Formatted string.
	 */
	function formatInterval( seconds ) {
		if ( seconds >= 86400 && seconds % 86400 === 0 ) {
			const days = seconds / 86400;
			return days + ' ' + ( days === 1 ? 'day' : wcsRreData.strings.days );
		}
		if ( seconds >= 3600 && seconds % 3600 === 0 ) {
			const hours = seconds / 3600;
			return hours + ' ' + ( hours === 1 ? 'hour' : wcsRreData.strings.hours );
		}
		const minutes = Math.round( seconds / 60 );
		return minutes + ' ' + ( minutes === 1 ? 'minute' : wcsRreData.strings.minutes );
	}

	/**
	 * Get the numeric value for an interval (in the best unit).
	 *
	 * @param {number} seconds Interval in seconds.
	 * @return {number} Value in the appropriate unit.
	 */
	function getIntervalValue( seconds ) {
		if ( seconds >= 86400 && seconds % 86400 === 0 ) {
			return seconds / 86400;
		}
		if ( seconds >= 3600 && seconds % 3600 === 0 ) {
			return seconds / 3600;
		}
		return Math.round( seconds / 60 );
	}

	/**
	 * Get the unit multiplier for an interval.
	 *
	 * @param {number} seconds Interval in seconds.
	 * @return {number} Unit multiplier (60, 3600, or 86400).
	 */
	function getIntervalUnit( seconds ) {
		if ( seconds >= 86400 && seconds % 86400 === 0 ) {
			return 86400;
		}
		if ( seconds >= 3600 && seconds % 3600 === 0 ) {
			return 3600;
		}
		return 60;
	}

	/**
	 * Attach event listeners to rendered elements.
	 */
	function attachEventListeners() {
		// Save button
		const saveBtn = document.getElementById( 'wcs-rre-save' );
		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', handleSave );
		}

		// Reset button
		const resetBtn = document.getElementById( 'wcs-rre-reset' );
		if ( resetBtn ) {
			resetBtn.addEventListener( 'click', handleReset );
		}

		// Add rule button
		const addBtn = document.getElementById( 'wcs-rre-add' );
		if ( addBtn ) {
			addBtn.addEventListener( 'click', handleAddRule );
		}

		// Delete buttons
		document.querySelectorAll( '.wcs-rre-delete' ).forEach( btn => {
			btn.addEventListener( 'click', handleDeleteRule );
		} );

		// Move up buttons
		document.querySelectorAll( '.wcs-rre-move-up' ).forEach( btn => {
			btn.addEventListener( 'click', handleMoveUp );
		} );

		// Move down buttons
		document.querySelectorAll( '.wcs-rre-move-down' ).forEach( btn => {
			btn.addEventListener( 'click', handleMoveDown );
		} );

		// Field change listeners
		document.querySelectorAll( '.wcs-rre-interval-value, .wcs-rre-interval-unit' ).forEach( el => {
			el.addEventListener( 'change', handleIntervalChange );
		} );

		document.querySelectorAll( '.wcs-rre-customer-email' ).forEach( el => {
			el.addEventListener( 'change', handleFieldChange( 'email_template_customer' ) );
		} );

		document.querySelectorAll( '.wcs-rre-admin-email' ).forEach( el => {
			el.addEventListener( 'change', handleFieldChange( 'email_template_admin' ) );
		} );

		document.querySelectorAll( '.wcs-rre-order-status' ).forEach( el => {
			el.addEventListener( 'change', handleFieldChange( 'status_to_apply_to_order' ) );
		} );

		document.querySelectorAll( '.wcs-rre-subscription-status' ).forEach( el => {
			el.addEventListener( 'change', handleFieldChange( 'status_to_apply_to_subscription' ) );
		} );

		document.querySelectorAll( '.wcs-rre-email-override-input' ).forEach( el => {
			el.addEventListener( 'change', handleEmailOverrideChange );
		} );

		document.querySelectorAll( '.wcs-rre-email-override-toggle' ).forEach( el => {
			el.addEventListener( 'change', handleEmailOverrideToggle );
		} );

		document.querySelectorAll( '.wcs-rre-email-preview' ).forEach( el => {
			el.addEventListener( 'click', handleEmailPreview );
		} );

		document.querySelectorAll( '.wcs-rre-modal-close' ).forEach( el => {
			el.addEventListener( 'click', handleModalClose );
		} );

		document.querySelectorAll( '.wcs-rre-modal-backdrop' ).forEach( el => {
			el.addEventListener( 'click', handleModalBackdropClick );
		} );
	}

	/**
	 * Handle interval field changes.
	 *
	 * @param {Event} e The change event.
	 */
	function handleIntervalChange( e ) {
		const index = parseInt( e.target.dataset.index, 10 );
		const card = e.target.closest( '.wcs-rre-rule-card' );
		const valueInput = card.querySelector( '.wcs-rre-interval-value' );
		const unitSelect = card.querySelector( '.wcs-rre-interval-unit' );

		const value = parseInt( valueInput.value, 10 ) || 5;
		const unit = parseInt( unitSelect.value, 10 );
		const seconds = value * unit;

		// Enforce minimum
		if ( seconds < config.min_interval ) {
			valueInput.value = Math.ceil( config.min_interval / unit );
			rules[ index ].retry_after_interval = config.min_interval;
		} else {
			rules[ index ].retry_after_interval = seconds;
		}

		markChanged();
		render();
	}

	/**
	 * Create a field change handler.
	 *
	 * @param {string} field The field name.
	 * @return {Function} Event handler.
	 */
	function handleFieldChange( field ) {
		return function( e ) {
			const index = parseInt( e.target.dataset.index, 10 );
			rules[ index ][ field ] = e.target.value;
			markChanged();
			render();
		};
	}

	/**
	 * Handle save button click.
	 */
	async function handleSave() {
		if ( isSaving ) return;

		isSaving = true;
		render();

		try {
			await apiFetch( '/rules', {
				method: 'POST',
				body: JSON.stringify( { rules } ),
			} );

			hasChanges = false;
			isDefault = false;
			showNotice( wcsRreData.strings.saveSuccess, 'success' );
		} catch ( error ) {
			showNotice( wcsRreData.strings.saveError + ' ' + error.message, 'error' );
		} finally {
			isSaving = false;
			render();
		}
	}

	/**
	 * Handle reset button click.
	 */
	async function handleReset() {
		if ( ! confirm( wcsRreData.strings.confirmReset ) ) {
			return;
		}

		try {
			const response = await apiFetch( '/reset', {
				method: 'POST',
			} );

			rules = response.rules || [];
			isDefault = true;
			hasChanges = false;
			showNotice( response.message, 'success' );
			render();
		} catch ( error ) {
			showNotice( error.message, 'error' );
		}
	}

	/**
	 * Handle add rule button click.
	 */
	function handleAddRule() {
		// Create new rule with sensible defaults
		const newRule = {
			retry_after_interval: 12 * 3600, // 12 hours
			email_template_customer: '',
			email_template_admin: 'WCS_Email_Payment_Retry',
			status_to_apply_to_order: 'pending',
			status_to_apply_to_subscription: 'on-hold',
			email_override_customer: false,
			email_override_admin: false,
			email_subject_customer: '',
			email_heading_customer: '',
			email_additional_content_customer: '',
			email_subject_admin: '',
			email_heading_admin: '',
			email_additional_content_admin: '',
		};

		rules.push( newRule );
		markChanged();
		render();

		// Scroll to new rule
		const cards = document.querySelectorAll( '.wcs-rre-rule-card' );
		if ( cards.length > 0 ) {
			cards[ cards.length - 1 ].scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
	}

	/**
	 * Handle delete rule button click.
	 *
	 * @param {Event} e The click event.
	 */
	function handleDeleteRule( e ) {
		if ( ! confirm( wcsRreData.strings.confirmDelete ) ) {
			return;
		}

		const index = parseInt( e.target.dataset.index, 10 );
		rules.splice( index, 1 );
		markChanged();
		render();
	}

	/**
	 * Handle move up button click.
	 *
	 * @param {Event} e The click event.
	 */
	function handleMoveUp( e ) {
		const index = parseInt( e.target.dataset.index, 10 );
		if ( index > 0 ) {
			[ rules[ index - 1 ], rules[ index ] ] = [ rules[ index ], rules[ index - 1 ] ];
			markChanged();
			render();
		}
	}

	/**
	 * Handle move down button click.
	 *
	 * @param {Event} e The click event.
	 */
	function handleMoveDown( e ) {
		const index = parseInt( e.target.dataset.index, 10 );
		if ( index < rules.length - 1 ) {
			[ rules[ index ], rules[ index + 1 ] ] = [ rules[ index + 1 ], rules[ index ] ];
			markChanged();
			render();
		}
	}

	/**
	 * Handle email override field changes.
	 *
	 * @param {Event} e The change event.
	 */
	function handleEmailOverrideChange( e ) {
		const index = parseInt( e.target.dataset.index, 10 );
		const field = e.target.dataset.field;

		if ( ! rules[ index ] ) {
			return;
		}

		rules[ index ][ field ] = e.target.value;
		markChanged();
		render();
	}

	/**
	 * Handle email override toggle.
	 *
	 * @param {Event} e The change event.
	 */
	function handleEmailOverrideToggle( e ) {
		const index = parseInt( e.target.dataset.index, 10 );
		const field = e.target.dataset.field;

		if ( ! rules[ index ] ) {
			return;
		}

		rules[ index ][ field ] = e.target.checked;
		markChanged();
		render();
	}

	/**
	 * Handle email preview click.
	 *
	 * @param {Event} e The click event.
	 */
	function handleEmailPreview( e ) {
		const index = parseInt( e.currentTarget.dataset.index, 10 );
		const recipient = e.currentTarget.dataset.recipient;

		if ( ! rules[ index ] || ! config.email_preview || ! config.email_preview[ recipient ] ) {
			return;
		}

		previewModal = {
			open: true,
			loading: true,
			error: '',
			html: '',
			subject: '',
			heading: '',
			ruleIndex: index,
			recipient,
		};
		render();

		apiFetch( '/email-preview', {
			method: 'POST',
			body: JSON.stringify( {
				recipient,
				rule: rules[ index ],
			} ),
		} )
			.then( response => {
				previewModal = {
					...previewModal,
					loading: false,
					error: '',
					html: response.html || '',
					subject: response.subject || '',
					heading: response.heading || '',
				};
				render();
			} )
			.catch( error => {
				previewModal = {
					...previewModal,
					loading: false,
					error: error.message || 'Preview failed',
				};
				render();
			} );
	}

	/**
	 * Handle modal close.
	 *
	 * @param {Event} e The click event.
	 */
	function handleModalClose() {
		if ( ! previewModal.open ) {
			return;
		}

		previewModal = {
			...previewModal,
			open: false,
		};
		render();
	}

	/**
	 * Close modal on backdrop click.
	 *
	 * @param {Event} e The click event.
	 */
	function handleModalBackdropClick( e ) {
		if ( e.target !== e.currentTarget ) {
			return;
		}

		handleModalClose();
	}

	/**
	 * Close modal on escape.
	 *
	 * @param {KeyboardEvent} e The event.
	 */
	function handleModalEscape( e ) {
		if ( e.key !== 'Escape' || ! previewModal.open ) {
			return;
		}

		previewModal = {
			...previewModal,
			open: false,
		};
		render();
	}

	/**
	 * Mark that there are unsaved changes.
	 */
	function markChanged() {
		hasChanges = true;
		isDefault = false;
	}

	/**
	 * Show a notice message.
	 *
	 * @param {string} message The message to show.
	 * @param {string} type    Notice type (success, error, warning).
	 */
	function showNotice( message, type = 'success' ) {
		// Remove existing notices
		document.querySelectorAll( '.wcs-rre-notice' ).forEach( el => el.remove() );

		const notice = document.createElement( 'div' );
		notice.className = `notice notice-${type} is-dismissible wcs-rre-notice`;
		notice.innerHTML = `<p>${escapeHtml( message )}</p><button type="button" class="notice-dismiss"></button>`;

		const wrap = document.querySelector( '.wcs-rre-wrap' );
		if ( wrap ) {
			wrap.insertBefore( notice, wrap.firstChild.nextSibling );

			// Add dismiss handler
			notice.querySelector( '.notice-dismiss' ).addEventListener( 'click', () => {
				notice.remove();
			} );

			// Auto-dismiss success messages
			if ( type === 'success' ) {
				setTimeout( () => notice.remove(), 5000 );
			}
		}
	}

	/**
	 * Escape HTML entities.
	 *
	 * @param {string} str String to escape.
	 * @return {string} Escaped string.
	 */
	function escapeHtml( str ) {
		const div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	/**
	 * Escape attribute values without encoding HTML tags.
	 *
	 * @param {string} str String to escape.
	 * @return {string} Escaped string.
	 */
	function escapeAttribute( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' );
	}

	// Initialize when DOM is ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
