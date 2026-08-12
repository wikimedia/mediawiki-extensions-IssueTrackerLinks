const Api = require( '../api.js' );

const EditLinkDialog = function ( cfg ) {
	cfg = cfg || {};
	this.mode = cfg.mode || 'add';
	this.record = cfg.record || null;

	EditLinkDialog.parent.call( this, cfg );
};

OO.inheritClass( EditLinkDialog, OO.ui.ProcessDialog );

EditLinkDialog.static.name = 'issueTrackerLinksEditLinkDialog';
EditLinkDialog.static.actions = [
	{
		action: 'save',
		label: mw.msg( 'issuetrackerlinks-config-dialog-action-save' ),
		flags: [ 'progressive', 'primary' ]
	},
	{
		action: 'cancel',
		label: mw.msg( 'issuetrackerlinks-config-dialog-action-cancel' ),
		flags: [ 'safe', 'close' ]
	}
];

EditLinkDialog.prototype.getSetupProcess = function () {
	return EditLinkDialog.parent.prototype.getSetupProcess.call( this ).next(
		function () {
			const title = this.mode === 'edit' ?
				mw.msg( 'issuetrackerlinks-config-dialog-link-title-edit' ) :
				mw.msg( 'issuetrackerlinks-config-dialog-link-title-add' );
			this.title.setLabel( title ).setTitle( title );
			this.actions.setAbilities( { save: false } );
		},
		this
	);
};

EditLinkDialog.prototype.initialize = function () {
	EditLinkDialog.parent.prototype.initialize.call( this );

	this.content = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true
	} );

	this.dataProviderStore = new OOJSPlus.ui.data.store.RemoteRestStore( {
		path: 'issuetrackerlinks/v0/config/dataproviders',
		cacheResults: false,
		sorter: {
			name: {
				direction: 'asc'
			}
		}
	} );
	this.dataProviderPicker = new OO.ui.DropdownInputWidget( {
		$overlay: this.$overlay,
		value: this.record && this.record.data_provider ? this.record.data_provider : '',
		options: [
			{
				data: '',
				label: mw.msg( 'issuetrackerlinks-config-data-provider-picker-loading' )
			}
		]
	} );
	this.dataProviderPicker.connect( this, { change: 'onInputChange' } );

	this.nameInput = new OO.ui.TextInputWidget( {
		required: true,
		value: this.record ? this.record.label : ''
	} );
	this.nameInput.connect( this, { change: 'onInputChange' } );
	this.urlInput = new OO.ui.TextInputWidget( {
		required: true,
		value: this.record ? this.record.url : ''
	} );
	this.urlInput.connect( this, { change: 'onInputChange' } );

	this.displayMaskInput = new OO.ui.TextInputWidget( {
		value: this.record ? this.record.display_mask : ''
	} );
	this.displayMaskInput.connect( this, { change: 'onInputChange' } );

	this.content.$element.append(
		new OO.ui.FieldLayout( this.nameInput, {
			label: mw.msg( 'issuetrackerlinks-config-grid-header-name' ),
			align: 'top'
		} ).$element,
		new OO.ui.FieldLayout( this.urlInput, {
			label: mw.msg( 'issuetrackerlinks-config-grid-header-url' ),
			align: 'top'
		} ).$element,
		new OO.ui.FieldLayout( this.displayMaskInput, {
			label: mw.msg( 'issuetrackerlinks-config-grid-header-display-mask' ),
			align: 'top'
		} ).$element,
		new OO.ui.FieldLayout( this.dataProviderPicker, {
			label: mw.msg( 'issuetrackerlinks-config-grid-header-data-provider' ),
			align: 'top'
		} ).$element
	);

	this.$body.append( this.content.$element );
	this.loadDataProviders();
};

EditLinkDialog.prototype.onInputChange = function () {
	this.actions.setAbilities( {
		save: !!this.nameInput.getValue() && !!this.urlInput.getValue()
	} );
};

EditLinkDialog.prototype.loadDataProviders = function () {
	this.dataProviderStore.loadAll().done( ( data ) => {
		const options = [
			{
				data: '',
				label: mw.msg( 'issuetrackerlinks-config-data-provider-picker-none' )
			}
		];

		for ( const key in data ) {
			if ( !Object.prototype.hasOwnProperty.call( data, key ) ) {
				continue;
			}
			const provider = data[ key ];
			options.push( {
				data: provider.name,
				label: provider.name
			} );
		}

		this.dataProviderPicker.setOptions( options );
		if ( this.record && this.record.data_provider ) {
			this.dataProviderPicker.setValue( this.record.data_provider );
		}
	} ).fail( () => {
		this.dataProviderPicker.setOptions( [ {
			data: '',
			label: mw.msg( 'issuetrackerlinks-config-data-provider-picker-load-failed' )
		} ] );
	} );
};

EditLinkDialog.prototype.getActionProcess = function ( action ) {
	return EditLinkDialog.super.prototype.getActionProcess
		.call( this, action )
		.next( () => {
			if ( action === 'save' ) {
				const dfd = $.Deferred();

				try {
					const data = {
						url: this.urlInput.getValue(),
						label: this.nameInput.getValue(),
						'display-mask': this.displayMaskInput.getValue(),
						'data-provider': this.dataProviderPicker.getValue()
					};
					const type = this.record ? 'update' : 'create';
					let promise;
					if ( type === 'update' ) {
						promise = Api.updateLink( this.record.key, data );
					} else {
						promise = Api.addLink( data );
					}
					promise.then( () => {
						this.close( { reload: true } );
					} );
				} catch ( e ) {
					mw.log.error( e );
					dfd.reject( new OO.ui.Error( mw.msg( 'issuetrackerlinks-config-error-save-link' ) ) );
				}
				return dfd.promise();
			}

			if ( action === 'cancel' ) {
				this.close();
			}
		}, this );
};

EditLinkDialog.prototype.getBodyHeight = function () {
	return this.$body[ 0 ].scrollHeight;
};

module.exports = EditLinkDialog;
