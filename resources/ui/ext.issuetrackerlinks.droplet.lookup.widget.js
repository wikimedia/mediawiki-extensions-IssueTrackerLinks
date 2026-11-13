ext.issuetrackerlinks.droplet.IssueLookupWidget = function ( config ) {
	config = config || {};
	config.allowSuggestionsWhenEmpty = true;
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

	const promise = deferred.promise();
	// OO.ui.mixin.RequestManager expects a request object with an .abort() method
	promise.abort = function () {};

	if ( !this.provider ) {
		deferred.resolve( [] );
		return promise;
	}

	const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
	if ( controller ) {
		promise.abort = function () {
			controller.abort();
		};
	}

	let url = mw.util.wikiScript( 'rest' ) + '/issuetrackerlinks/v0/search-issues/' +
		encodeURIComponent( this.provider );

	if ( query ) {
		url += '?query=' + encodeURIComponent( query );
	}

	fetch( url, controller ? { signal: controller.signal } : {} )
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
			if ( error && error.name === 'AbortError' ) {
				deferred.reject( error );
				return;
			}
			this.emit( 'searchError', error );
			deferred.reject( error );
		} );

	return promise;
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
