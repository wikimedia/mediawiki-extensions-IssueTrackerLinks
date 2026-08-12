const Api = {
	deleteLink: async ( linkId ) => Api.post( '/config/link-editor', {
		action: 'delete',
		key: linkId,
		data: {}
	} ),
	addLink: async ( data ) => Api.post( '/config/link-editor', {
		action: 'create',
		data: data
	} ),
	updateLink: async ( linkId, data ) => Api.post( '/config/link-editor', {
		action: 'update',
		key: linkId,
		data: data
	} ),
	getDataProviderInfo: async ( dataProviderId ) => Api.get( '/config/data-provider/' + encodeURIComponent( dataProviderId ) ),
	createDataProvider: async ( name, type, data ) => Api.post( '/config/data-provider-editor', {
		action: 'create',
		name: name,
		type: type,
		data: data
	} ),
	deleteDataProvider: async ( dataProviderId ) => Api.post( '/config/data-provider-editor', {
		action: 'delete',
		name: dataProviderId,
		data: {}
	} ),
	getDataProviderBackends: async () => Api.get( '/config/data-provider-backend' ),
	getDataProviderBackendData: async ( dataProviderId ) => Api.get( '/config/data-provider-backend/' + encodeURIComponent( dataProviderId ) ),
	post: async ( url, data ) => {
		const fullUrl = mw.util.wikiScript( 'rest' ) + '/issuetrackerlinks/v0' + url;
		const response = await fetch( fullUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify( data )
		} );
		if ( !response.ok ) {
			const error = await response.json();
			// Read and throw the error
			throw new Error(
				error.message ||
				mw.msg( 'issuetrackerlinks-config-error-save-data-provider' )
			);
		}
		return response.json();
	},
	get: async ( url ) => {
		const fullUrl = mw.util.wikiScript( 'rest' ) + '/issuetrackerlinks/v0' + url;
		return await fetch( fullUrl ).then( ( response ) => response.json() );
	}
};

module.exports = Api;
