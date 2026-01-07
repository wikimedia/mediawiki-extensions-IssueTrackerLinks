ext.issuetrackerlinks.ve.Sequence = function ( sequencePattern, key ) {
	this.key = key;
	ext.issuetrackerlinks.ve.Sequence.super.apply( this, [
		'insert' + key + 'Sequence',
		'insert' + key + 'Command',
		sequencePattern,
		undefined,
		{
			setSelection: true
		} ]
	);
};

OO.inheritClass( ext.issuetrackerlinks.ve.Sequence, ve.ui.Sequence );

ext.issuetrackerlinks.ve.Sequence.prototype.execute = function ( surface, range ) {
	// Get text on range
	const text = surface.getModel().getLinearFragment( range ).getText();
	const matches = text.match( new RegExp( ext.issuetrackerlinks.util.makeSequenceRegex( this.data ) ) );
	if ( !matches ) {
		return;
	}
	const params = matches.groups || {};
	if ( Object.keys( params ).length === 0 ) {
		// No parameters extracted, do not execute
		return false;
	}
	const content = [ {
		type: 'issue',
		attributes: {
			mw: {
				attrs: {
					type: this.key,
					params: ext.issuetrackerlinks.util.serializeParams( params )
				}
			}
		}
	} ];
	// Remove current range, add content
	surface.getModel().change(
		ve.dm.TransactionBuilder.static.newFromReplacement(
			surface.getModel().getDocument(), range, content, true
		)
	);
	const newSelection = surface.getModel().getSelection().collapseToEnd();
	const newRange = newSelection.getRange();
	const newFragment = surface.getModel().getLinearFragment( newRange, true, true );

	newFragment.insertContent( ' ' ).collapseToEnd().select();
};

ext.issuetrackerlinks.ve.Sequence.prototype.match = function ( data, offset, plaintext ) {
	const sequenceRegexSpace =
		new RegExp( ext.issuetrackerlinks.util.makeSequenceRegex( this.data ) + '\\s' );
	const i = plaintext.search( sequenceRegexSpace );
	if ( i >= 0 ) {
		return new ve.Range( offset - plaintext.length + i, offset );
	}
	return null;
};

// Register all sequences for patterns that have it configured
const patterns = require( './../patterns.json' );

for ( const key in patterns ) {
	const sequence = patterns[ key ].sequence || null;
	if ( !sequence ) {
		// No sequence defined
		continue;
	}
	// Check if sequence is only variables, without additional content
	const regex = new RegExp( '{.+?}', 'g' ); // eslint-disable-line prefer-regex-literals
	if ( sequence.replace( regex, '' ).trim().length === 0 ) {
		// Sequence is only variables, skip
		console.warn( 'Skipped sequence for ' + key + ' as it contains only variables.', sequence ); // eslint-disable-line no-console
		continue;
	}
	ve.ui.sequenceRegistry.register( new ext.issuetrackerlinks.ve.Sequence( sequence, key ) );
}
