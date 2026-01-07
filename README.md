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
