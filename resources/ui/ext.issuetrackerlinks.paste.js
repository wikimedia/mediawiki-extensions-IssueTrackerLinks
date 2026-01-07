let matchedPattern = null;

ext.issuetrackerlinks.ve.PasteHandler = function () {
	ext.issuetrackerlinks.ve.PasteHandler.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ext.issuetrackerlinks.ve.PasteHandler, ve.ui.HTMLStringTransferHandler );

/* Static properties */
ext.issuetrackerlinks.ve.PasteHandler.static.name = 'issueTrackerLink';
ext.issuetrackerlinks.ve.PasteHandler.static.types = [ 'text/plain' ];
ext.issuetrackerlinks.ve.PasteHandler.static.handlesPaste = true;
ext.issuetrackerlinks.ve.PasteHandler.static.matchFunction = function ( item ) {
	const patterns = require( './../patterns.json' );
	for ( const key in patterns ) {
		const regex = ext.issuetrackerlinks.util.patternToRegex( patterns[ key ].url );
		if ( regex.test( item.getAsString().trim() ) ) {
			matchedPattern = Object.assign( { type: key }, patterns[ key ] );
			return true;
		}
	}
	return false;
};

/**
 * @inheritdoc
 */
ext.issuetrackerlinks.ve.PasteHandler.prototype.process = function () {
	const text = this.item.getAsString().trim();
	if ( !matchedPattern ) {
		this.resolve( text );
		return;
	}
	const params = ext.issuetrackerlinks.util.extractUrlParams( matchedPattern.url, text );
	if ( !params ) {
		this.resolve( text );
		return;
	}
	const result = [ {
		type: 'issue',
		attributes: {
			mw: {
				attrs: {
					type: matchedPattern.type,
					params: ext.issuetrackerlinks.util.serializeParams( params )
				}
			}
		}
	} ];
	matchedPattern = null;
	this.resolve( result );
};

/* Registration */
ve.ui.dataTransferHandlerFactory.register( ext.issuetrackerlinks.ve.PasteHandler );
