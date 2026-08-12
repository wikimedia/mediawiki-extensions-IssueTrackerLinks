const Api = require( '../api.js' );

const DataProviderDetailsDialog = function ( cfg ) {
	cfg = cfg || {};
	this.provider = cfg;
	this.providerName = cfg.name || '';

	DataProviderDetailsDialog.parent.call( this, cfg );
};

OO.inheritClass( DataProviderDetailsDialog, OO.ui.Dialog );

DataProviderDetailsDialog.static.name = 'issueTrackerLinksDataProviderDetailsDialog';
DataProviderDetailsDialog.static.title = mw.msg( 'issuetrackerlinks-config-dialog-data-provider-details-title' );
DataProviderDetailsDialog.static.actions = [ {
	action: 'close',
	label: mw.msg( 'issuetrackerlinks-config-dialog-action-close' ),
	flags: [ 'primary', 'progressive' ]
} ];

DataProviderDetailsDialog.prototype.initialize = function () {
	DataProviderDetailsDialog.parent.prototype.initialize.call( this );

	const closeButton = new OO.ui.ButtonWidget( {
		icon: 'close',
		framed: false,
		title: mw.msg( 'issuetrackerlinks-config-dialog-action-close' ),
		flags: [ 'safe', 'close' ]
	} );
	closeButton.connect( this, { click: 'close' } );
	this.$body.append( new OO.ui.HorizontalLayout( {
		items: [ closeButton ],
		classes: [ 'issuetrackerlinks-config-data-provider-details-close-button' ]
	} ).$element );

	this.content = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true
	} );
	this.$body.append( this.content.$element );
};

DataProviderDetailsDialog.prototype.getSetupProcess = function ( data ) {
	return DataProviderDetailsDialog.parent.prototype.getSetupProcess.call( this, data ).next(
		function () {
			return this.loadData();
		},
		this
	);
};

DataProviderDetailsDialog.prototype.loadData = function () {

	if ( !this.providerName ) {
		this.renderError( mw.msg( 'issuetrackerlinks-config-data-provider-details-error' ) );
		return $.Deferred().resolve().promise();
	}

	return Api.getDataProviderInfo( this.providerName ).then( ( data ) => {
		this.renderData( data );
	} ).catch( () => {
		this.renderError( mw.msg( 'issuetrackerlinks-config-data-provider-details-error' ) );
	} );
};

DataProviderDetailsDialog.prototype.renderData = function ( data ) {
	this.content.$element.empty();

	if ( !data || data.error || data.message ) {
		this.renderError( mw.msg( 'issuetrackerlinks-config-data-provider-details-error' ) );
		return;
	}

	this.content.$element.append(
		new OO.ui.FieldLayout(
			new OO.ui.LabelWidget( { label: data.name || this.providerName } ),
			{
				label: mw.msg( 'issuetrackerlinks-config-provider-grid-header-name' ),
				align: 'left'
			}
		).$element,
		new OO.ui.FieldLayout(
			new OO.ui.LabelWidget( { label: data.type || '' } ),
			{
				label: mw.msg( 'issuetrackerlinks-config-provider-grid-header-type' ),
				align: 'left'
			}
		).$element
	);

	if ( data.viewData ) {
		this.content.$element.append(
			$( '<div>' )
				.addClass( 'issuetrackerlinks-config-data-provider-details-view' )
				.html( data.viewData )
		);
	}

	if ( data.redirect_uri ) {
		this.content.$element.append(
			new OO.ui.MessageWidget( {
				type: 'notice',
				label: new OO.ui.HtmlSnippet(
					mw.msg( 'issuetrackerlinks-config-dialog-redirect-uri-message', data.redirect_uri )
				),
				classes: [ 'issuetrackerlinks-config-data-provider-redirect-notice' ],
				inline: true
			} ).$element
		);
	}
};

DataProviderDetailsDialog.prototype.renderError = function ( text ) {
	this.content.$element.empty().append(
		new OO.ui.MessageWidget( {
			type: 'error',
			label: text
		} ).$element
	);
};

DataProviderDetailsDialog.prototype.getBodyHeight = function () {
	return this.$body[ 0 ].scrollHeight;
};

module.exports = DataProviderDetailsDialog;
