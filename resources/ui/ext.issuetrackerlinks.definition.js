ext.issuetrackerlinks.droplet.TagDefinition = function ( cfg ) {
	ext.issuetrackerlinks.droplet.TagDefinition.super.call( this, cfg );
	this.patterns = require( './../patterns.json' );
};

OO.inheritClass(
	ext.issuetrackerlinks.droplet.TagDefinition, ext.visualEditorPlus.ui.tag.Definition
);

ext.issuetrackerlinks.droplet.TagDefinition.prototype.modifyDataBeforeSetToModel =
	function ( data ) {
		const url = data.url || '';
		const type = data.type || '';
		if ( !url || !type || !this.patterns[ type ] ) {
			return {
				type: type,
				params: ''
			};
		}
		const params = ext.issuetrackerlinks.util.extractUrlParams(
			this.patterns[ type ].url, url
		);
		if ( !params ) {
			return {
				type: type,
				params: ''
			};
		}
		return {
			type: type,
			params: ext.issuetrackerlinks.util.serializeParams( params )
		};

	};

ext.issuetrackerlinks.droplet.TagDefinition.prototype.modifyDataBeforeSetToInspector =
	function ( data, inspector ) {
		inspector.inspectorForm.onUpdateValue( data );
		const type = data.type || inspector.commandParams.type || '';
		if ( !type || !this.patterns[ type ] ) {
			data.params = '';
			data.url = '';
			return data;
		}
		if ( !data.params ) {
			data.params = '';
			data.url = '';
			return data;
		}
		const params = ext.issuetrackerlinks.util.deserializeParams( data.params || '' );
		data.url = ext.issuetrackerlinks.util.replaceUrlParams( this.patterns[ type ].url, params );
		return data;
	};

ext.issuetrackerlinks.droplet.TagDefinition.prototype.updateMwData =
	function ( inspector, mwData ) {
		ext.issuetrackerlinks.droplet.TagDefinition.super.prototype.updateMwData.call(
			this, inspector, mwData
		);
		// URL is only used for inspector, not part of actual tag attrs
		delete ( mwData.attrs.url );
	};

mw.hook( 'ext.visualEditorPlus.tags.registerTags' ).add( ( _registry, tags ) => {
	for ( let i = 0; i < tags.length; i++ ) {
		const tag = tags[ i ];
		if ( tag.tags.indexOf( 'issue' ) !== -1 ) {
			_registry.registerTagDefinition(
				new ext.issuetrackerlinks.droplet.TagDefinition( tag.clientSpecification )
			);
		}
	}
} );
