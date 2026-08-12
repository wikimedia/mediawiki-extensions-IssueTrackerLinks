$( () => {
	const container = document.getElementById( 'bs-issuetrackerlinks-config-grid' );
	if ( !container ) {
		return;
	}

	const ConfigPanel = require( './ui/special/ConfigPanel.js' );
	const panel = new ConfigPanel();
	$( container ).append( panel.$element );
} );
