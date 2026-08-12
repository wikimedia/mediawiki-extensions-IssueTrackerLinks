<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderStore;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;

class ListDataProviderBackends extends SimpleHandler {
	use AdminAction;

	/**
	 * @param DataProviderStore $dataProviderStore
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		private readonly DataProviderStore $dataProviderStore,
		PermissionManager $permissionManager
	) {
		$this->initPermissionsChecker( $permissionManager );
	}

	/**
	 * @return true
	 */
	public function needsWriteAccess() {
		return true;
	}

	/**
	 * @return Response
	 * @throws HttpException
	 */
	public function execute() {
		$this->assertIsAdmin( RequestContext::getMain()->getAuthority() );
		return $this->getResponseFactory()->createJson( $this->dataProviderStore->listProviderBackends() );
	}
}
