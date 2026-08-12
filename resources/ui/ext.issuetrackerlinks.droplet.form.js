require( './ext.issuetrackerlinks.droplet.lookup.widget.js' );

ext.issuetrackerlinks.droplet.Form = function ( config ) {
	ext.issuetrackerlinks.droplet.Form.super.call( this, {
		definition: {
			buttons: []
		}
	} );
	this.patterns = require( './../patterns.json' );
	this.inspector = config.inspector;
	this.patternRegex = null;
	this.innerForm = null;
	this.commandParams = this.inspector.commandParams || {};
	this.lookupFieldLayout = null;
	this.lookupWidget = null;
	this.lookupOptionSelected = false;
	this.providerLookupRequestId = 0;
	this.lastSearchError = null;
	this.urlLabelItem = null;
	this.urlChangeConnected = false;
	this.lookupAuthPanel = null;
	this.lookupAuthorizeButton = null;
	this.lookupAuthProvider = '';
	this.authWindow = null;
	this.onAuthMessageHandler = this.onAuthMessage.bind( this );
	window.addEventListener( 'message', this.onAuthMessageHandler );

	this.$linkPreview = $( '<a>' )
		.addClass( 'issuetrackerlinks-inspector-link-preview' )
		.attr( {
			target: '_blank',
			rel: 'noopener'
		} )
		.hide();
};

OO.inheritClass( ext.issuetrackerlinks.droplet.Form, mw.ext.forms.standalone.Form );

ext.issuetrackerlinks.droplet.Form.prototype.makeItems = function () {
	return [
		{
			type: 'text',
			hidden: true,
			name: 'type'
		},
		{
			name: 'params',
			hidden: true,
			type: 'text'
		},
		{
			type: 'label',
			name: 'urlLabel',
			widget_label: mw.msg( 'issuetrackerlinks-field-url' ) // eslint-disable-line camelcase
		},
		{
			type: 'text',
			name: 'url',
			required: true,
			noLayout: true,
			widget_validate: function ( value ) { // eslint-disable-line camelcase
				if ( this.patternRegex === null ) {
					return true;
				}
				return this.patternRegex.test( value );
			}.bind( this )
		}
	];
};

ext.issuetrackerlinks.droplet.Form.prototype.onRenderComplete = function ( form ) {
	this.innerForm = form;
	const initValue = form.getItem( 'type' ).getValue();
	const newValue = initValue || this.commandParams.type || '';
	if ( newValue ) {
		this.setInspectorTitle( newValue );
		this.setPatternRegex( newValue );
	}

	if ( !this.urlLabelItem ) {
		this.urlLabelItem = form.getItem( 'urlLabel' ) || null;
	}

	if ( !this.$linkPreview.parent().length ) {
		// Append clickable link preview above the URL input
		form.getItem( 'url' ).$element.before( this.$linkPreview );
	}
	if ( !this.urlChangeConnected ) {
		form.getItem( 'url' ).connect( this, { change: 'updateLinkPreview' } );
		this.urlChangeConnected = true;
	}
	this.updateLinkPreview( form.getItem( 'url' ).getValue() );
	this.refreshProviderInputMode();

	setTimeout( () => {
		// Exec in next loop
		if ( initValue !== newValue ) {
			form.getItem( 'type' ).setValue( newValue );
		}
		// Set correct pattern for URL validation based on type
		const type = form.getItem( 'type' ).getValue();
		this.setPatternRegex( type );
		this.refreshProviderInputMode();
	}, 1 );
	this.inspector.commandParams = {};
};

ext.issuetrackerlinks.droplet.Form.prototype.onUpdateValue = function ( data ) {
	this.commandParams = this.inspector.commandParams;
	if ( !this.innerForm ) {
		return;
	}
	const type = data.type || this.commandParams.type || '';
	if ( type ) {
		const typeConfig = this.patterns[ type ] || null;
		if ( typeConfig ) {
			this.setInspectorTitle( type );
			this.setPatternRegex( type );
		}
		this.refreshProviderInputMode();
	}
};

ext.issuetrackerlinks.droplet.Form.prototype.setInspectorTitle = function ( key ) {
	let typeLabel = key;
	const pattern = this.patterns[ key ] || null;
	if ( pattern && pattern.label ) {
		const msg = mw.message( pattern.label );
		if ( msg.exists() ) {
			typeLabel = msg.text();
		} else {
			typeLabel = pattern.label;
		}
	}

	this.inspector.title.setLabel( mw.msg( 'issuetrackerlinks-inspector-header-title', typeLabel ) );
};

ext.issuetrackerlinks.droplet.Form.prototype.setPatternRegex = function ( type ) {
	const pattern = this.patterns[ type ] || null;
	if ( pattern ) {
		this.patternRegex = ext.issuetrackerlinks.util.patternToRegex( pattern.url );
		return;
	}
	this.patternRegex = null;
};

ext.issuetrackerlinks.droplet.Form.prototype.updateLinkPreview = function ( url ) {
	const label = this.getLinkPreviewLabel( url );
	if ( label ) {
		this.$linkPreview
			.attr( 'href', url )
			.text( label )
			.show();
	} else {
		this.$linkPreview.hide();
	}
};

ext.issuetrackerlinks.droplet.Form.prototype.getLinkPreviewLabel = function ( url ) {
	const type = this.innerForm ? this.innerForm.getItem( 'type' ).getValue() : '';
	const pattern = type ? ( this.patterns[ type ] || null ) : null;
	if ( url && pattern && this.patternRegex && this.patternRegex.test( url ) ) {
		const params = ext.issuetrackerlinks.util.extractUrlParams( pattern.url, url );
		let label = url;
		if ( params && pattern[ 'display-mask' ] ) {
			label = pattern[ 'display-mask' ].replace(
				/\{(\w[\w-]*)\}/g,
				( _match, key ) => params[ key ] || ''
			);
		}
		return label;
	}
	return '';
};

ext.issuetrackerlinks.droplet.Form.prototype.getCurrentDataProvider = function () {
	const type = this.innerForm ? this.innerForm.getItem( 'type' ).getValue() : '';
	const pattern = this.patterns[ type ] || {};

	return this.commandParams.dataProvider ||
		this.commandParams[ 'data-provider' ] ||
		pattern.dataProvider ||
		pattern[ 'data-provider' ] ||
		'';
};

ext.issuetrackerlinks.droplet.Form.prototype.refreshProviderInputMode = function () {
	const dataProvider = this.getCurrentDataProvider();

	if ( !dataProvider ) {
		this.disableLookupMode();
		return;
	}

	const requestId = ++this.providerLookupRequestId;
	const url = mw.util.wikiScript( 'rest' ) + '/issuetrackerlinks/v0/config/data-provider/' +
		encodeURIComponent( dataProvider );

	fetch( url )
		.then( ( response ) => {
			if ( !response.ok ) {
				throw new Error( 'Failed to load provider details for "' + dataProvider + '"' );
			}
			return response.json();
		} )
		.then( ( providerData ) => {
			if ( requestId !== this.providerLookupRequestId ) {
				return;
			}
			if ( providerData && providerData.searchable ) {
				this.enableLookupMode( dataProvider );
				return;
			}
			this.disableLookupMode();
		} )
		.catch( () => {
			if ( requestId !== this.providerLookupRequestId ) {
				return;
			}
			this.disableLookupMode();
		} );
};

ext.issuetrackerlinks.droplet.Form.prototype.enableLookupMode = function ( provider ) {
	const urlWidget = this.innerForm.getItem( 'url' );
	if ( !this.lookupWidget ) {
		this.lookupWidget = new ext.issuetrackerlinks.droplet.IssueLookupWidget( {
			$overlay: true
		} );
		this.lookupWidget.connect( this, {
			searchError: 'onLookupSearchError',
			change: 'onLookupInputChange'
		} );
		this.lookupWidget.lookupMenu.connect( this, {
			choose: 'onLookupOptionChoose'
		} );

		this.lookupFieldLayout = new OO.ui.FieldLayout( this.lookupWidget, {
			label: mw.msg( 'issuetrackerlinks-inspector-search-label' ),
			align: 'top'
		} );
		urlWidget.$element.before( this.lookupFieldLayout.$element );
	}

	this.lookupWidget.setProvider( provider );
	this.hideLookupAuthPrompt();
	this.lookupFieldLayout.$element.show();
	if ( this.urlLabelItem ) {
		this.urlLabelItem.$element.hide();
	}
	urlWidget.$element.hide();

	const currentUrl = urlWidget.getValue();
	this.lookupOptionSelected = true;
	this.lookupWidget.setValue( currentUrl ? this.getLinkPreviewLabel( currentUrl ) : '' );
	this.lookupOptionSelected = false;
};

ext.issuetrackerlinks.droplet.Form.prototype.disableLookupMode = function () {
	const urlWidget = this.innerForm ? this.innerForm.getItem( 'url' ) : null;
	if ( !urlWidget ) {
		return;
	}
	this.hideLookupAuthPrompt();
	if ( this.lookupFieldLayout ) {
		this.lookupFieldLayout.$element.hide();
	}
	if ( this.urlLabelItem ) {
		this.urlLabelItem.$element.show();
	}
	urlWidget.$element.show();
};

ext.issuetrackerlinks.droplet.Form.prototype.onLookupSearchError = function ( error ) {
	if ( error && ( error.status === 401 || error.code === 'no_auth' ) ) {
		this.showLookupAuthPrompt( this.getCurrentDataProvider() );
		return;
	}
	if ( this.lastSearchError === error.message ) {
		return;
	}
	this.lastSearchError = error.message;
	mw.log.error( error );
	mw.notify( mw.msg( 'issuetrackerlinks-inspector-search-error' ), { type: 'error' } );
};

ext.issuetrackerlinks.droplet.Form.prototype.showLookupAuthPrompt = function ( provider ) {
	if ( !provider || !this.lookupFieldLayout || !this.lookupWidget ) {
		return;
	}
	if ( !this.lookupAuthPanel ) {
		this.lookupAuthorizeButton = new OO.ui.ButtonWidget( {
			label: mw.msg( 'issuetrackerlinks-authorize-button', provider ),
			flags: [ 'progressive', 'primary' ],
			icon: 'lock'
		} );
		this.lookupAuthorizeButton.$element.on( 'click', ( e ) => {
			e.preventDefault();
			const authorizationTitle = mw.Title.makeTitle( -1, 'IssueAuth/start' );
			const authUrl = authorizationTitle.getUrl( { provider: this.lookupAuthProvider } );
			this.openAuthWindow( authUrl );
		} );

		this.lookupAuthPanel = new OO.ui.PanelLayout( {
			expanded: false,
			padded: true,
			classes: [ 'issuetrackerlinks-popup-authorization-panel' ]
		} );
		this.lookupAuthPanel.$element.append( this.lookupAuthorizeButton.$element );
		this.lookupFieldLayout.$element.before( this.lookupAuthPanel.$element );
	}

	this.lookupAuthProvider = provider;
	if ( this.lookupAuthorizeButton ) {
		this.lookupAuthorizeButton.setLabel( mw.msg( 'issuetrackerlinks-authorize-button', provider ) );
	}
	this.lookupFieldLayout.$element.hide();
	this.lookupAuthPanel.$element.show();
};

ext.issuetrackerlinks.droplet.Form.prototype.hideLookupAuthPrompt = function () {
	if ( this.lookupAuthPanel ) {
		this.lookupAuthPanel.$element.hide();
	}
	this.lookupAuthProvider = '';
};

ext.issuetrackerlinks.droplet.Form.prototype.openAuthWindow = function ( authUrl ) {
	this.authWindow = window.open( authUrl, '_blank' );
	if ( this.authWindow ) {
		this.authWindow.focus();
	}
};

ext.issuetrackerlinks.droplet.Form.prototype.onAuthMessage = function ( event ) {
	if ( event.origin !== window.location.origin ) {
		return;
	}

	const data = event.data || {};
	if ( data.type !== 'issuetrackerlinks.oauth.done' ) {
		return;
	}
	if ( !this.lookupAuthProvider || data.provider !== this.lookupAuthProvider ) {
		return;
	}

	this.hideLookupAuthPrompt();
	if ( this.lookupFieldLayout ) {
		this.lookupFieldLayout.$element.show();
	}
	if ( this.lookupWidget && typeof this.lookupWidget.populateLookupMenu === 'function' ) {
		this.lookupWidget.populateLookupMenu();
	}
	window.focus();
};

ext.issuetrackerlinks.droplet.Form.prototype.onLookupInputChange = function () {
	if ( this.lookupOptionSelected || !this.innerForm ) {
		return;
	}
	const lookupElement = this.lookupFieldLayout ? this.lookupFieldLayout.$element.get( 0 ) : null;
	if ( !lookupElement || lookupElement.style.display === 'none' ) {
		return;
	}
	this.innerForm.getItem( 'url' ).setValue( '' );
	this.updateLinkPreview( '' );
};

ext.issuetrackerlinks.droplet.Form.prototype.onLookupOptionChoose = function ( option ) {
	if ( !option || !this.innerForm ) {
		return;
	}

	const data = option.getData() || {};
	const type = this.innerForm.getItem( 'type' ).getValue();
	const pattern = this.patterns[ type ] || null;
	if ( !pattern ) {
		return;
	}

	const url = ext.issuetrackerlinks.util.replaceUrlParams( pattern.url, data );
	if ( !this.patternRegex || !this.patternRegex.test( url ) ) {
		mw.notify( mw.msg( 'issuetrackerlinks-inspector-invalid-search-result' ), { type: 'error' } );
		return;
	}

	this.lookupOptionSelected = true;
	this.lookupWidget.setValue( option.getLabel() );
	this.lookupOptionSelected = false;
	this.innerForm.getItem( 'url' ).setValue( url );
	this.updateLinkPreview( url );
};
