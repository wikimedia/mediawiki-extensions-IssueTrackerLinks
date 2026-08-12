const EditLinkDialog = require( './dialog/EditLinkDialog.js' );
const Api = require( './api.js' );

const IssueLinksGrid = function ( cfg ) {
	cfg = cfg || {};

	this.store = new OOJSPlus.ui.data.store.RemoteRestStore( {
		path: 'issuetrackerlinks/v0/config/issuelinks',
		cacheResults: false
	} );

	const columns = {
		label: {
			type: 'text',
			headerText: mw.msg( 'issuetrackerlinks-config-grid-header-name' ),
			filter: { type: 'string' },
			sortable: true,
			display: 'label_parsed'
		},
		url: {
			type: 'text',
			headerText: mw.msg( 'issuetrackerlinks-config-grid-header-url' ),
			filter: { type: 'string' },
			sortable: true
		},
		display_mask: { // eslint-disable-line camelcase
			type: 'text',
			headerText: mw.msg( 'issuetrackerlinks-config-grid-header-display-mask' ),
			filter: { type: 'string' },
			sortable: true
		},
		data_provider: { // eslint-disable-line camelcase
			type: 'text',
			headerText: mw.msg( 'issuetrackerlinks-config-grid-header-data-provider' ),
			filter: { type: 'string' },
			sortable: true
		},
		actionEdit: {
			type: 'action',
			title: mw.msg( 'issuetrackerlinks-config-grid-action-edit' ),
			actionId: 'edit',
			icon: 'edit',
			headerText: mw.msg( 'issuetrackerlinks-config-grid-action-edit' ),
			invisibleHeader: true,
			width: 30,
			visibleOnHover: true
		},
		actionDelete: {
			type: 'action',
			title: mw.msg( 'issuetrackerlinks-config-grid-action-delete' ),
			actionId: 'delete',
			icon: 'trash',
			headerText: mw.msg( 'issuetrackerlinks-config-grid-action-delete' ),
			invisibleHeader: true,
			width: 30,
			visibleOnHover: true
		}
	};

	IssueLinksGrid.parent.call( this, Object.assign( {
		expanded: false,
		padded: false,
		grid: {
			store: this.store,
			multiSelect: false,
			columns: columns
		}
	}, cfg ) );
};

OO.inheritClass( IssueLinksGrid, OOJSPlus.ui.panel.ManagerGrid );

IssueLinksGrid.prototype.getToolbarActions = function () {
	return [
		this.getAddAction( {
			displayBothIconAndLabel: true,
			title: mw.msg( 'issuetrackerlinks-config-grid-action-add-issue-link' )
		} )
	];
};

IssueLinksGrid.prototype.onAction = function ( action, row ) {
	if ( action === 'add' ) {
		this.openEditDialog();
		return;
	}
	if ( action === 'edit' && row ) {
		this.openEditDialog( row );
		return;
	}
	if ( action === 'delete' && row ) {
		this.openDeleteDialog( row );
	}
};

IssueLinksGrid.prototype.openEditDialog = function ( row ) {
	const isEdit = !!row;
	const dialog = new EditLinkDialog( {
		mode: isEdit ? 'edit' : 'add',
		record: row || null
	} );
	this.openDialog( dialog );
};

IssueLinksGrid.prototype.openDeleteDialog = function ( row ) {
	OO.ui.confirm(
		mw.msg( 'issuetrackerlinks-config-remove-link-confirm', row.label_parsed ), {
			title: mw.msg( 'issuetrackerlinks-config-remove-link-confirm-header' ),
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
				await Api.deleteLink( row.key );
				this.store.reload();
			} catch ( e ) {
				mw.notify( mw.msg( 'issuetrackerlinks-config-error-delete-link' ), { type: 'error' } );
				mw.log.error( e );
			}
		} );
};

IssueLinksGrid.prototype.openDialog = function ( dialog ) {
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

module.exports = IssueLinksGrid;
