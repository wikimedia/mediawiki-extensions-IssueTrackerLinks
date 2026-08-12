<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\IssueTrackerLinks\Auth\RedirectUriProvider;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderStore;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class GetProviderBackendData extends SimpleHandler {
	use AdminAction;

	/**
	 * @param DataProviderStore $dataProviderStore
	 * @param RedirectUriProvider $redirectUriProvider
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		private readonly DataProviderStore $dataProviderStore,
		private readonly RedirectUriProvider $redirectUriProvider,
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
		$params = $this->getValidatedParams();
		$provider = $this->dataProviderStore->makeProviderBackend( $params['type'] );
		if ( !$provider ) {
			throw new HttpException( 'Provider not found', 404 );
		}

		return $this->getResponseFactory()->createJson( [
			'type' => $provider->getKey(),
			'form' => $provider->getEditForm()?->getSerialized(),
			'redirect_uri' => $this->redirectUriProvider->getRedirectUri( $provider ),
		] );
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'type' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}
}
