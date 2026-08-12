<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Rest;

use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;

trait AdminAction {

	/** @var PermissionManager */
	private readonly PermissionManager $permissionManager;

	/**
	 * @param PermissionManager $permissionManager
	 * @return void
	 */
	protected function initPermissionsChecker( PermissionManager $permissionManager ) {
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @param Authority $user
	 * @return void
	 * @throws HttpException
	 */
	protected function assertIsAdmin( Authority $user ): void {
		if ( !$this->permissionManager->userHasRight( $user->getUser(), 'wikiadmin' ) ) {
			throw new HttpException( 'permissiondenied', 403 );
		}
	}
}
