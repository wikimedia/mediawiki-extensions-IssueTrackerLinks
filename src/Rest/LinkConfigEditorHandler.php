<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\IssueTrackerLinks\ConfigJsonMutator;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class LinkConfigEditorHandler extends SimpleHandler {
	use AdminAction;

	/**
	 * @param ConfigJsonMutator $configJsonMutator
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		private readonly ConfigJsonMutator $configJsonMutator,
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
		$body = $this->getValidatedBody();

		$action = $body['action'];
		$key = $body['key'] ?? null;
		$data = $body['data'];

		if ( $action !== 'create' && !$key ) {
			throw new HttpException( 'Missing key', 400 );
		}
		$this->configJsonMutator->setActor( RequestContext::getMain()->getUser() );
		switch ( $action ) {
			case 'create':
				$this->configJsonMutator->addLinkConfig(
					uniqid(),
					$data
				);
				break;
			case 'update':
				$this->configJsonMutator->updateLinkConfig(
					$key,
					$data
				);
				break;
			case 'delete':
				$this->configJsonMutator->removeLinkConfig( $key );
		}

		return $this->getResponseFactory()->createJson( [ 'success' => true ] );
	}

	/**
	 * @return array[]
	 */
	public function getBodyParamSettings(): array {
		return [
			'action' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'key' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'data' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}
}
