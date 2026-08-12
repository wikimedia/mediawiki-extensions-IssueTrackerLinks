const patterns = require( './patterns.json' );

for ( const key in patterns ) {
	ve.ui.commandRegistry.register(
		new ve.ui.Command(
			key + 'Command', 'window', 'open',
			{
				args: [
					'issueInspector',
					{
						commandParams: {
							type: key,
							dataProvider: patterns[ key ][ 'data-provider' ] || ''
						}
					}
				]
			}
		)
	);
}
