<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\IssueTrackerLinks\Data\IssueLinks\Store as LinkStore;
use MediaWiki\Extension\IssueTrackerLinks\PatternConfig;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\QueryStore;
use MWStake\MediaWiki\Component\DataStore\IStore;

class IssueLinksConfigStore extends QueryStore {
	use AdminAction;

	/**
	 * @param HookContainer $hookContainer
	 * @param PatternConfig $patternConfig
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		HookContainer $hookContainer,
		private readonly PatternConfig $patternConfig,
		PermissionManager $permissionManager
	) {
		parent::__construct( $hookContainer );
		$this->initPermissionsChecker( $permissionManager );
	}

	/**
	 * @return Response|mixed
	 * @throws HttpException
	 */
	public function execute() {
		$this->assertIsAdmin( RequestContext::getMain()->getAuthority() );
		return parent::execute();
	}

	/**
	 * @return IStore
	 */
	protected function getStore(): IStore {
		return new LinkStore( $this->patternConfig );
	}
}
