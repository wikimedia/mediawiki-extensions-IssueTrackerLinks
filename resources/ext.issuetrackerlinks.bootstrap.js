window.ext = window.ext || {};
ext.issuetrackerlinks = {
	droplet: {},
	ve: {},
	util: {
		patternToRegex: function ( pattern ) {
			// Escape special regex characters except for our {variables}
			const escaped = pattern.replace( /[-/\\^$+?.()|[\]{}]/g, '\\$&' );

			// Replace escaped {variable} placeholders with capture groups
			const regexPattern = escaped.replace( /\\{(\w+)\\}/g, '([^/]+)' );

			// Anchor it to match the whole string
			return new RegExp( `^${ regexPattern }$` );
		},
		extractUrlParams: function ( pattern, url ) {
			// Step 1: extract variable names
			const keys = ( pattern.match( /\{(\w+)\}/g ) || [] ).map( ( k ) => k.slice( 1, -1 ) );

			// Step 2: escape regex special chars in pattern except braces
			const escapedPattern = pattern.replace( /[-/\\^$+?.()|[\]]/g, '\\$&' );

			// Step 3: replace {var} placeholders with regex groups
			const regexPattern = escapedPattern.replace( /\{(\w+)\}/g, '([^/]+)' );

			// Step 4: build full regex
			const regex = new RegExp( `^${ regexPattern }$` );

			// Step 5: match URL
			const match = url.match( regex );
			if ( !match ) {
				return null;
			}

			// Step 6: map captured groups to variable names
			return keys.reduce( ( acc, key, i ) => {
				acc[ key ] = match[ i + 1 ];
				return acc;
			}, {} );
		},
		replaceUrlParams: function ( pattern, params ) {
			let url = pattern;
			for ( const key in params ) {
				if ( !Object.prototype.hasOwnProperty.call( params, key ) ) {
					continue;
				}
				const placeholder = `{${ key }}`;
				url = url.replace( placeholder, params[ key ] );
			}
			return url;
		},
		serializeParams: function ( params ) {
			// Params are key-value pairs, serialize to "key1:value1;key2;value2"
			const result = [];
			for ( const key in params ) {
				if ( Object.prototype.hasOwnProperty.call( params, key ) ) {
					result.push( key + ':' + params[ key ] );
				}
			}
			return result.join( ';' );
		},
		deserializeParams: function ( str ) {
			const params = {};
			const pairs = str.split( ';' );
			for ( let i = 0; i < pairs.length; i++ ) {
				const [ key, value ] = pairs[ i ].split( ':' );
				if ( key && value ) {
					params[ key ] = value;
				}
			}
			return params;
		},
		makeSequenceRegex: function ( pattern ) {
			// Replace all {var} variables with `(?<var>[A-Za-z0-9_-]+)`
			return pattern.replace( /\{(\w+)\}/g, '(?<$1>[A-Za-z0-9_-]+)' );
		}
	}
};
