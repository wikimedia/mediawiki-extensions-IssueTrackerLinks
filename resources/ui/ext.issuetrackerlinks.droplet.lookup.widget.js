ext.issuetrackerlinks.droplet.IssueLookupWidget = function ( config ) {
	config = config || {};
	this.provider = config.provider || '';

	ext.issuetrackerlinks.droplet.IssueLookupWidget.super.call( this, config );
	OO.ui.mixin.LookupElement.call( this, config );
};

OO.inheritClass( ext.issuetrackerlinks.droplet.IssueLookupWidget, OO.ui.TextInputWidget );
OO.mixinClass( ext.issuetrackerlinks.droplet.IssueLookupWidget, OO.ui.mixin.LookupElement );

ext.issuetrackerlinks.droplet.IssueLookupWidget.prototype.setProvider = function ( provider ) {
	this.provider = provider || '';
};

ext.issuetrackerlinks.droplet.IssueLookupWidget.prototype.getLookupRequest = function () {
	const query = this.getValue().trim();
	const deferred = $.Deferred();

	if ( !this.provider || !query ) {
		return deferred.resolve( [] ).promise();
	}

	const url = mw.util.wikiScript( 'rest' ) + '/issuetrackerlinks/v0/search-issues/' +
		encodeURIComponent( this.provider ) + '?query=' + encodeURIComponent( query );

	fetch( url )
		.then( ( response ) => {
			if ( !response.ok ) {
				return response.json().catch( () => ( {} ) ).then( ( errorData ) => {
					const code = errorData && typeof errorData.message === 'string' ?
						errorData.message :
						'';
					const error = new Error(
						code || 'Issue search request failed with status ' + response.status
					);
					error.status = response.status;
					error.code = code;
					throw error;
				} );
			}
			return response.json();
		} )
		.then( ( data ) => {
			if ( !Array.isArray( data ) ) {
				throw new Error( 'Issue search request returned invalid payload' );
			}
			deferred.resolve( data );
		} )
		.catch( ( error ) => {
			this.emit( 'searchError', error );
			deferred.reject( error );
		} );

	return deferred.promise();
};

ext.issuetrackerlinks.droplet.IssueLookupWidget.prototype.getLookupCacheDataFromResponse =
	function ( response ) {
		return response;
	};

ext.issuetrackerlinks.droplet.IssueLookupWidget.prototype.getLookupMenuOptionsFromData =
	function ( data ) {
		if ( !Array.isArray( data ) ) {
			return [];
		}

		return data.map( ( item ) => new OO.ui.MenuOptionWidget( {
			label: item.label || '',
			data: item.data || {}
		} ) );
	};
