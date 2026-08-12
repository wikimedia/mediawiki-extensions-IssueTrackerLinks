<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderStore;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class DataProviderConfigEditorHandler extends SimpleHandler {
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
		$body = $this->getValidatedBody();

		$action = $body['action'];
		$name = $body['name'] ?? null;
		$type = $body['type'] ?? null;
		$data = $body['data'];

		if ( $action == 'create' && !$type ) {
			throw new HttpException( 'Missing type', 400 );
		}

		if ( $action === 'create' ) {
			$backend = $this->dataProviderStore->makeProviderBackend( $type );
			if ( !$backend->isValidConfiguration( $data ) ) {
				throw new HttpException(
					Message::newFromKey( 'issuetrackerlinks-invalid-data-provider-config' )->text(),
					400
				);
			}
			foreach ( $data as $key => $value ) {
				$data[$key] = trim( $value );
			}
			$this->dataProviderStore->addProvider(
				str_replace( '_', ' ', trim( $name ) ), trim( $type ), $data
			);
		} elseif ( $action === 'delete' ) {
			$this->dataProviderStore->deleteProvider( $name );
		} else {
			throw new HttpException( 'Invalid action', 400 );
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
			'name' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'type' => [
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
