ext.issuetrackerlinks.droplet.Form = function ( config ) {
	ext.issuetrackerlinks.droplet.Form.super.call( this, {
		definition: {
			buttons: []
		}
	} );
	this.patterns = require( './../patterns.json' );
	this.inspector = config.inspector;
	this.patternRegex = null;
	this.innerForm = null;
	this.commandParams = this.inspector.commandParams || {};
};

OO.inheritClass( ext.issuetrackerlinks.droplet.Form, mw.ext.forms.standalone.Form );

ext.issuetrackerlinks.droplet.Form.prototype.makeItems = function () {
	return [
		{
			type: 'text',
			hidden: true,
			name: 'type'
		},
		{
			name: 'params',
			hidden: true,
			type: 'text'
		},
		{
			type: 'label',
			widget_label: mw.msg( 'issuetrackerlinks-field-url' ) // eslint-disable-line camelcase
		},
		{
			type: 'text',
			name: 'url',
			required: true,
			noLayout: true,
			widget_validate: function ( value ) { // eslint-disable-line camelcase
				if ( this.patternRegex === null ) {
					return true;
				}
				return this.patternRegex.test( value );
			}.bind( this )
		}
	];
};

ext.issuetrackerlinks.droplet.Form.prototype.onRenderComplete = function ( form ) {
	this.innerForm = form;
	const initValue = form.getItem( 'type' ).getValue();
	let newValue = initValue;
	if ( !initValue || this.commandParams.type ) {
		newValue = this.commandParams.type || '';
	}
	if ( newValue ) {
		this.setInspectorTitle( newValue );
		this.setPatternRegex( newValue );
	}
	setTimeout( () => {
		// Exec in next loop
		if ( initValue !== newValue ) {
			form.getItem( 'type' ).setValue( newValue );
		}
		// Set correct pattern for URL validation based on type
		this.setPatternRegex( form.getItem( 'type' ).getValue() );
	}, 1 );
	this.inspector.commandParams = {};
};

ext.issuetrackerlinks.droplet.Form.prototype.onUpdateValue = function ( data ) {
	this.commandParams = this.inspector.commandParams;
	if ( !this.innerForm ) {
		return;
	}
	const type = data.type || this.commandParams.type || '';
	this.onRenderComplete( this.innerForm );
	if ( type ) {
		const typeConfig = this.patterns[ type ] || null;
		if ( typeConfig ) {
			this.setInspectorTitle( type );
		}

	}
};

ext.issuetrackerlinks.droplet.Form.prototype.setInspectorTitle = function ( key ) {
	let typeLabel = key;
	const pattern = this.patterns[ key ] || null;
	if ( pattern && pattern.label ) {
		const msg = mw.message( pattern.label ); // eslint-disable-line mediawiki/msg-doc
		if ( msg.exists() ) {
			typeLabel = msg.text();
		} else {
			typeLabel = pattern.label;
		}
	}

	this.inspector.title.setLabel( mw.msg( 'issuetrackerlinks-inspector-header-title', typeLabel ) );
};

ext.issuetrackerlinks.droplet.Form.prototype.setPatternRegex = function ( type ) {
	const pattern = this.patterns[ type ] || null;
	if ( pattern ) {
		this.patternRegex = ext.issuetrackerlinks.util.patternToRegex( pattern.url );
	}
};
