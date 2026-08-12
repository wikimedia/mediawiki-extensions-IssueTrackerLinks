const CreateDataProviderDialog = require( './dialog/CreateDataProviderDialog.js' );
const DataProviderDetailsDialog = require( './dialog/DataProviderDetailsDialog.js' );
const Api = require( './api.js' );

const DataProvidersGrid = function ( cfg ) {
	cfg = cfg || {};

	this.store = new OOJSPlus.ui.data.store.RemoteRestStore( {
		path: 'issuetrackerlinks/v0/config/dataproviders',
		cacheResults: true,
		sorter: {
			name: {
				direction: 'asc'
			}
		}
	} );

	const columns = {
		name: {
			type: 'text',
			headerText: mw.msg( 'issuetrackerlinks-config-provider-grid-header-name' ),
			filter: { type: 'string' },
			sortable: true
		},
		type: {
			type: 'text',
			headerText: mw.msg( 'issuetrackerlinks-config-provider-grid-header-type' ),
			filter: { type: 'string' },
			sortable: true
		},
		actionInfo: {
			type: 'action',
			title: mw.msg( 'issuetrackerlinks-config-provider-grid-action-info' ),
			actionId: 'info',
			icon: 'info',
			headerText: mw.msg( 'issuetrackerlinks-config-provider-grid-action-info' ),
			invisibleHeader: true,
			width: 30,
			visibleOnHover: true
		},
		actionDelete: {
			type: 'action',
			title: mw.msg( 'issuetrackerlinks-config-provider-grid-action-delete' ),
			actionId: 'delete',
			icon: 'trash',
			headerText: mw.msg( 'issuetrackerlinks-config-provider-grid-action-delete' ),
			invisibleHeader: true,
			width: 30,
			visibleOnHover: true
		}
	};

	DataProvidersGrid.parent.call( this, Object.assign( {
		expanded: false,
		padded: false,
		grid: {
			store: this.store,
			multiSelect: false,
			columns: columns
		}
	}, cfg ) );
};

OO.inheritClass( DataProvidersGrid, OOJSPlus.ui.panel.ManagerGrid );

DataProvidersGrid.prototype.getToolbarActions = function () {
	return [
		this.getAddAction( {
			displayBothIconAndLabel: true,
			title: mw.msg( 'issuetrackerlinks-config-provider-grid-action-add-dataprovider' )
		} )
	];
};

DataProvidersGrid.prototype.onAction = function ( action, row ) {
	if ( action === 'add' ) {
		const dialog = new CreateDataProviderDialog();
		this.openDialog( dialog );
		return;
	}
	if ( action === 'info' && row ) {
		const dialog = new DataProviderDetailsDialog( row );
		this.openDialog( dialog );
		return;
	}
	if ( action === 'delete' && row ) {
		this.openDeleteDialog( row );
	}
};

DataProvidersGrid.prototype.openDeleteDialog = function ( row ) {
	OO.ui.confirm(
		mw.msg( 'issuetrackerlinks-config-remove-data-provider-confirm', row.name ), {
			title: mw.msg( 'issuetrackerlinks-config-remove-data-provider-confirm-header' ),
			size: 'medium',
			actions: [
				{
					label: mw.msg( 'issuetrackerlinks-config-dialog-action-delete' ),
					flags: [ 'destructive' ],
					action: 'accept'
				},
				{
					label: mw.msg( 'issuetrackerlinks-config-dialog-action-cancel' ),
					action: 'cancel'
				}
			]
		} )
		.done( async ( confirmed ) => {
			if ( !confirmed ) {
				return;
			}
			try {
				await Api.deleteDataProvider( row.name );
				this.store.reload();
			} catch ( e ) {
				mw.notify( mw.msg( 'issuetrackerlinks-config-error-delete-link' ), { type: 'error' } );
				mw.log.error( e );
			}
		} );
};

DataProvidersGrid.prototype.openDialog = function ( dialog ) {
	if ( !this.windowManager ) {
		this.windowManager = OO.ui.getWindowManager();
	}

	this.windowManager.addWindows( [ dialog ] );
	this.windowManager.openWindow( dialog ).closed.then( ( res ) => {
		if ( res && res.reload ) {
			this.store.reload();
		}
		this.windowManager.removeWindows( [ dialog.name ] );
	} );
};

module.exports = DataProvidersGrid;
