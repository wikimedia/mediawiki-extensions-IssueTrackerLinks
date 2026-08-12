<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Hook;

use MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\IDataProviderBackend;

interface IssueTrackerLinksAuthRedirectUriHook {

	/**
	 * @param IDataProviderBackend $provider
	 * @param string &$redirectUri
	 * @return void
	 */
	public function onIssueTrackerLinksAuthRedirectUri(
		IDataProviderBackend $provider, string &$redirectUri
	): void;
}
