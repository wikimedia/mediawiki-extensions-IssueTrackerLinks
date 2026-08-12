<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\IssueTrackerLinks\Auth\RedirectUriProvider;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\SearchableBackendProvider;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderStore;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class GetDataProviderData extends SimpleHandler {
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

		$provider = $this->dataProviderStore->getProvider( str_replace( '_', ' ', $params['name'] ) );
		if ( !$provider ) {
			throw new HttpException( 'Provider not found', 404 );
		}

		OutputPage::setupOOUI();
		return $this->getResponseFactory()->createJson( [
			'id' => $provider->getId(),
			'name' => $provider->getName(),
			'type' => $provider->getBackend()->getKey(),
			'viewData' => $provider->getBackend()->getViewLayout()->toString(),
			'redirect_uri' => $this->redirectUriProvider->getRedirectUri( $provider->getBackend() ),
			'searchable' => $provider->getBackend() instanceof SearchableBackendProvider,
		] );
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'name' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}
}
