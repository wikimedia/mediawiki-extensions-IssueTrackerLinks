# IssueTrackerLinks

Handles rendering of issue tracker links from content.

## Configuration

Configuration is done exclusively on `MediaWiki:IssueTrackerLinksConfig.json` page.
This page will be pre-created and filled with common tracker types.

Configuration is a JSON object, where key is a unique ID for the tracker and value is an object with the following properties:

- `url` - mandatory - URL pattern for the tracker links. Any variable parts can be replaced with a variable, eg. `{repo}`, `{id}`...
`"https://github.com/{owner}/{repo}/issues/{id}"`
- `label` - optional - message key or string message to be used as a label for this tracker type
- `display-mask` - optional - how this tracker link should be represented in page, eg. `Github: {id}`, would render it as `Github: 6`
- `sequence` - optional - if you want to be able to auto-convert certain sequences in VisualEditor to tracker links, specify
mask here. Eg. if mask is defined as `GH{id}`, then any `GH123` sequence will be converted to a link to the tracker with `id=123`,
or `GH:{repo},{id}` for `GH:example-repo,123`, where `repo=example-repo` and `id=123`. 
Note that if the URL mask contains more variables than present in sequence, that will lead to broken links.
Recommended is to use sequences only for URLs with one variable, eg. issue IDs.

# Data providers

It is possible to configure data providers for certain link types. This allows fetching details about the issue,
which is shown directly in the wiki, instead of having just a simple link.

## Defining a data provider

Providers can be defined in UI, on Special:IssueTrackerLinksConfig page, or on server-side.

Providers can be defined server-side and the configured under `data-provider` key in link configuration.
Providers can be created for any of the defined backend types implemented.

Create a data provider by creating a json file with all necessary information and then running a maintenance script to register it.

Note: Before doing this, create the app on the target system (eg. OAuth application), which will provide the information
needed to register the provider.

Callback URI for OAuth providers on target system should be set to: `https://<wiki-url>/wiki/Special:IssueAuth/callback`

`/tmp/my-providers.json`

```json
[
	{
		"name": "MyOpenProject",
		"type": "openproject",
		"data": {
			"client_id": "Hf2PlPk0o...xkrijXLIY1MmdWzHnI",
			"client_secret": "DN_gTihXsKfKJ...vaBm7sC2QQ",
			"base_url": "http://openproject.example.com"
		}
	},
    ...
]
```

`php maintenance/run.php ./extensions/IssueTrackerLinks/maintenance/dataProviders -i /tmp/my-providers.json`

Then you can set `data-provider: "MyOpenProject"` in the link configuration.

For OAuth providers, possible configuration fields are:
- `client_id`
- `client_secret`
- `base_url`
- `authorize_url` - default: `/oauth/authorize`
- `access_token_url`- default: `/oauth/token`
- `scopes` - default: empty
- `scope_separator` - default: `{space}`
- `insecure_tls` - default: `false`; set to `true` to disable TLS certificate verification (for OAuth token exchange and provider API requests)

## Registering new data provider backends

Implement a class implementing `IssueTrackerLinks\DataProvider\IDataProvider` interface
and register it in `IssueTrackerLinksDataProviderBackend` attribute.

Afterwards, you can use the provider key as the `type` when registering a new provider.

# Fetching pages where issue links are present

Use this api to retrieve pages that contain links to issues in a given tracker. The API is available at:

`/issuetrackerlinks/v0/issue-pages/{selector_type}/{selector_id}`, where:

- `selector_type` is one of `link_type` or `provider`
  - `link_type` is a key configured in IssueTrackerLinks config
  - `provider` is the name of the data provider
- `selector_id` is the value of the selector, link key or provider name

`.../rest.php/issuetrackerlinks/v0/issue-pages/link_type/openproject?params={"project":"test-project", "id":"38"}`
``.../rest.php/issuetrackerlinks/v0/issue-pages/provider/MyOpenProject?params={"project":"test-project", "id":"38"}``


```json
[
	{
		"page_title": "Foo",
		"page_namespace": 0,
		"wiki_id": "wiki_mywiki",
		"url": "http://.../wiki/Foo",
		"params": {
			"project": "test-project",
			"id": "38"
		}
	}
]
```
