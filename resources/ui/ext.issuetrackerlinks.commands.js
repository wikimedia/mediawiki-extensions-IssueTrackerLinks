ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'githubCommand', 'window', 'open',
		{ args: [ 'issueInspector', { commandParams: { type: 'github' } } ] }
	)
);

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'gitlabCommand', 'window', 'open',
		{ args: [ 'issueInspector', { commandParams: { type: 'gitlab' } } ] }
	)
);

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'jiraCommand', 'window', 'open',
		{ args: [ 'issueInspector', { commandParams: { type: 'jira' } } ] }
	)
);
