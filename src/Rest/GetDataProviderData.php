<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\IssueTrackerLinks\Auth\RedirectUriProvider;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\SearchableBackendProvider;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderStore;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
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
	 * @return bool
	 */
	public function needsReadAccess() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function needsWriteAccess() {
		return false;
	}

	/**
	 * @return Response
	 * @throws HttpException
	 */
	public function execute() {
		$params = $this->getValidatedParams();

		$checkSearchable = $params['searchable'] ?? false;
		if ( $checkSearchable ) {
			$this->assertIsEditor( RequestContext::getMain()->getAuthority() );
		} else {
			$this->assertIsAdmin( RequestContext::getMain()->getAuthority() );
		}

		$provider = $this->dataProviderStore->getProvider( str_replace( '_', ' ', $params['name'] ) );
		if ( !$provider ) {
			throw new HttpException( 'Provider not found', 404 );
		}
		if ( $checkSearchable ) {
			return $this->getResponseFactory()->createJson( [
				'name' => $provider->getName(),
				'searchable' => $provider->getBackend() instanceof SearchableBackendProvider,
			] );
		}

		OutputPage::setupOOUI();
		return $this->getResponseFactory()->createJson( [
			'id' => $provider->getId(),
			'name' => $provider->getName(),
			'type' => $provider->getBackend()->getKey(),
			'viewData' => $provider->getBackend()->getViewLayout()->toString(),
			'redirect_uri' => $this->redirectUriProvider->getRedirectUri( $provider->getBackend() ),
		] );
	}

	/**
	 * @param Authority $user
	 * @return void
	 * @throws HttpException
	 */
	private function assertIsEditor( Authority $user ): void {
		if ( !$this->permissionManager->userHasRight( $user->getUser(), 'edit' ) ) {
			throw new HttpException( 'permissiondenied', 403 );
		}
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
			],
			'searchable' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => false,
			]
		];
	}
}
