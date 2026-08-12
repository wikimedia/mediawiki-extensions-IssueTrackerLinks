const PopupContent = function ( cfg ) {
	PopupContent.super.call( this, { padded: false, expanded: false } );

	this.providerType = cfg.providerType;
	this.directUrl = cfg.url;
	this.makeLayout();
	this.fetchParams = cfg.params;
	this.authWindow = null;
	this.onAuthMessageHandler = this.onAuthMessage.bind( this );
	window.addEventListener( 'message', this.onAuthMessageHandler );
	this.$element.append( this.$contentCnt );

	this.fetched = false;
	this.$element.addClass( 'issuetrackerlinks-popup-content' );
	this.fetch();
};

OO.inheritClass( PopupContent, OO.ui.PanelLayout );

PopupContent.prototype.makeLayout = function () {
	const opener = new OO.ui.ButtonWidget( {
		title: mw.msg( 'issuetrackerlinks-opener-label', this.providerType ),
		classes: [ 'issuetrackerlinks-opener' ],
		flags: [ 'progressive' ],
		icon: 'newWindow',
		target: '_blank',
		href: this.directUrl,
		framed: false
	} );

	this.$header = $( '<div>' ).addClass( 'issuetrackerlinks-popup-header' );
	this.titleLabel = new OO.ui.LabelWidget( {
		classes: [ 'issuetrackerlinks-issue-entity-title' ]
	} );
	this.$header.append( this.titleLabel.$element, opener.$element );
	this.$contentCnt = $( '<div>' );

	this.$element.append( this.$header, this.$contentCnt );
};

PopupContent.prototype.fetch = function () {
	const process = async () => {
		const url = mw.util.wikiScript( 'rest' ) + '/issuetrackerlinks/v0/get-issue';
		const postData = {
			entityData: this.fetchParams,
			provider: this.providerType
		};

		const res = await fetch( url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify( postData )
		} );

		// Capture fails as well
		if ( !res.ok ) {
			// Get the error message
			const error = await res.json();
			if ( error.message === 'no_auth' ) {
				this.appendAuthorizeButton();
				return;
			}
			if ( error.message === 'not_found' ) {
				this.$contentCnt.html( new OO.ui.MessageWidget( {
					label: mw.msg( 'issuetrackerlinks-ui-entity-not-found' ),
					type: 'error'
				} ).$element );
				this.emit( 'notFound' );
			}
			if ( error.message === 'forbidden' ) {
				this.$contentCnt.html( new OO.ui.MessageWidget( {
					label: mw.msg( 'issuetrackerlinks-ui-entity-not-allowed' ),
					type: 'error'
				} ).$element );
				this.emit( 'forbidden' );
			}
			return;
		}
		this.$header.show();
		const data = await res.json();
		this.providedBy = data.providedBy;

		this.render( data );
		this.fetched = true;
		this.emit( 'loaded', data, data.isClosed );
	};
	process();
};

PopupContent.prototype.appendAuthorizeButton = function () {
	this.$header.hide();
	const authorizationTitle = mw.Title.makeTitle( -1, 'IssueAuth/start' );
	const authUrl = authorizationTitle.getUrl( { provider: this.providerType } );

	const authorizeButton = new OO.ui.ButtonWidget( {
		label: mw.msg( 'issuetrackerlinks-authorize-button', this.providerType ),
		flags: [ 'progressive', 'primary' ],
		icon: 'lock'
	} );
	authorizeButton.$element.on( 'click', ( e ) => {
		e.preventDefault();
		this.openAuthWindow( authUrl );
	} );

	const opener = new OO.ui.ButtonWidget( {
		label: mw.msg( 'issuetrackerlinks-opener-label', this.providerType ),
		flags: [ 'progressive' ],
		target: '_blank',
		href: this.directUrl,
		framed: false
	} );

	const authorizationPanel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true,
		classes: [ 'issuetrackerlinks-popup-authorization-panel' ]
	} );
	authorizationPanel.$element.append(
		authorizeButton.$element,
		opener.$element
	);

	this.$contentCnt.html( authorizationPanel.$element );
};

PopupContent.prototype.render = function ( data ) {
	this.$contentCnt.empty();
	this.titleLabel.setLabel( data.title );

	this.$contentCnt.append( data.html );
};

PopupContent.prototype.openAuthWindow = function ( authUrl ) {
	this.authWindow = window.open( authUrl, '_blank' );
	if ( this.authWindow ) {
		this.authWindow.focus();
	}
};

PopupContent.prototype.onAuthMessage = function ( event ) {
	if ( event.origin !== window.location.origin ) {
		return;
	}
	const data = event.data || {};
	if ( data.type !== 'issuetrackerlinks.oauth.done' ) {
		return;
	}
	if ( data.provider !== this.providerType ) {
		return;
	}
	this.fetch();
	window.focus();
};

module.exports = PopupContent;
