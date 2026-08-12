<?php

namespace MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend;

use MediaWiki\Permissions\Authority;
use MediaWiki\Request\WebRequest;

interface OAuthProviderBackend extends IDataProviderBackend {

	/**
	 * @param WebRequest $request
	 * @param array $requestParams
	 * @return mixed
	 */
	public function authorize( WebRequest $request, array $requestParams );

	/**
	 * @param WebRequest $request
	 * @param Authority $forUser
	 * @param array $requestParams
	 * @return void
	 */
	public function handleAuthorizationCallback( WebRequest $request, Authority $forUser, array $requestParams ): void;
}
