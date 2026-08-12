const Api = require( '../api.js' );

const CreateDataProviderDialog = function ( cfg ) {
	cfg = cfg || {};

	CreateDataProviderDialog.parent.call( this, cfg );
};

OO.inheritClass( CreateDataProviderDialog, OO.ui.ProcessDialog );

CreateDataProviderDialog.static.title = mw.msg( 'issuetrackerlinks-config-dialog-title-add-data-provider' );
CreateDataProviderDialog.static.name = 'issueTrackerLinksCreateDataProviderDialog';
CreateDataProviderDialog.static.actions = [
	{
		action: 'save',
		label: mw.msg( 'issuetrackerlinks-config-dialog-action-add' ),
		flags: [ 'progressive', 'primary' ]
	},
	{
		action: 'cancel',
		label: mw.msg( 'issuetrackerlinks-config-dialog-action-cancel' ),
		flags: [ 'safe', 'close' ]
	}
];

CreateDataProviderDialog.prototype.getSetupProcess = function () {
	return CreateDataProviderDialog.parent.prototype.getSetupProcess.call( this ).next(
		function () {
			this.actions.setAbilities( { save: false } );
		},
		this
	);
};

CreateDataProviderDialog.prototype.initialize = function () {
	CreateDataProviderDialog.parent.prototype.initialize.call( this );

	this.content = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true
	} );

	this.typePicker = new OO.ui.DropdownInputWidget( {
		$overlay: this.$overlay
	} );
	this.typePicker.connect( this, { change: 'onTypeChange' } );
	this.nameInput = new OO.ui.TextInputWidget( {
		required: true
	} );
	this.nameInput.connect( this, { change: function ( value ) {
		this.actions.setAbilities( { save: !!value } );
	} } );
	this.$form = $( '<div>' );

	this.content.$element.append(
		new OO.ui.FieldLayout( this.nameInput, {
			label: mw.msg( 'issuetrackerlinks-config-grid-header-name' ),
			align: 'top'
		} ).$element,
		new OO.ui.FieldLayout( this.typePicker, {
			label: mw.msg( 'issuetrackerlinks-config-grid-header-type' ),
			align: 'top'
		} ).$element,
		this.$form
	);
	this.loadTypeOptions();

	this.$body.append( this.content.$element );
};

CreateDataProviderDialog.prototype.loadTypeOptions = function () {
	const loader = async () => {
		const backends = await Api.getDataProviderBackends();
		const options = [];
		for ( const backend of backends ) {
			options.push( {
				data: backend,
				label: backend
			} );
		}
		this.typePicker.setOptions( options );
	};
	loader();
};

CreateDataProviderDialog.prototype.onTypeChange = function ( type ) {
	const formLoader = async ( providerType ) => {
		this.$form.empty();
		this.updateSize();
		this.form = null;

		const data = await Api.getDataProviderBackendData( providerType );
		if ( !data.form ) {
			return;
		}

		this.form = new mw.ext.forms.standalone.Form( data.form );
		this.form.connect( this, {
			renderComplete: function ( form ) {
				for ( const inputKey in form.items.inputs || {} ) {
					form.items.inputs[ inputKey ].connect( this, {
						change: () => this.emit( 'updateSize' ),
						focus: () => this.emit( 'updateSize' ),
						blur: () => this.emit( 'updateSize' )
					} );
				}
				this.emit( 'updateSize' );
			}
		} );
		this.form.render();
		this.$form.append( this.form.$element );
		if ( data.redirect_uri ) {
			this.$form.append( new OO.ui.MessageWidget( {
				label: new OO.ui.HtmlSnippet(
					mw.msg( 'issuetrackerlinks-config-dialog-redirect-uri-message', data.redirect_uri )
				),
				classes: [ 'issuetrackerlinks-config-data-provider-redirect-notice' ],
				inline: true
			} ).$element );
		}
		this.updateSize();
	};

	formLoader( type );
};

CreateDataProviderDialog.prototype.getActionProcess = function ( action ) {
	return CreateDataProviderDialog.super.prototype.getActionProcess
		.call( this, action )
		.next( () => {
			if ( action === 'save' ) {
				const dfd = $.Deferred();
				const processor = async () => {
					const formData = await this.getFormData();
					await Api.createDataProvider(
						this.nameInput.getValue() || crypto.randomUUID(),
						this.typePicker.getValue(),
						formData
					);
				};
				processor()
					.then( () => {
						this.close( { reload: true } );
					} )
					.catch( ( e ) => {
						mw.log.error( e );
						dfd.reject(
							new OO.ui.Error( e )
						);
					} );

				return dfd.promise();
			}

			if ( action === 'cancel' ) {
				this.close();
			}
		}, this );
};

CreateDataProviderDialog.prototype.getFormData = function () {
	const dfd = $.Deferred();
	if ( !this.form ) {
		return dfd.resolve( {} ).promise();
	}
	this.form.connect( this, {
		dataSubmitted: function ( data ) {
			dfd.resolve( data );
		}
	} );
	this.form.submit();
	return dfd.promise();
};

CreateDataProviderDialog.prototype.getBodyHeight = function () {
	return this.$body[ 0 ].scrollHeight;
};

module.exports = CreateDataProviderDialog;
