<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Auth;

use MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\IDataProviderBackend;
use MediaWiki\HookContainer\HookContainer;

class RedirectUriProvider {

	/**
	 * @param \Config $config
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		private readonly \Config $config,
		private readonly HookContainer $hookContainer
	) {
	}

	/**
	 * @param IDataProviderBackend $providerBackend
	 * @return string
	 */
	public function getRedirectUri( IDataProviderBackend $providerBackend ): string {
		$articlePath = $this->config->get( 'ArticlePath' );
		$titlePath = str_replace( '$1', 'Special:IssueAuth/callback', $articlePath );
		$redirectUri = $this->config->get( 'Server' ) . $titlePath;
		$this->hookContainer->run( 'IssueTrackerLinksAuthRedirectUri', [ $providerBackend, &$redirectUri ] );

		return $redirectUri;
	}
}
