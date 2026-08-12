<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\IssueTrackerLinks\Auth\Exception\EntityForbiddenException;
use MediaWiki\Extension\IssueTrackerLinks\Auth\Exception\EntityNotFoundException;
use MediaWiki\Extension\IssueTrackerLinks\Auth\Exception\NoAuthException;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderStore;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class GetIssueHandler extends SimpleHandler {

	/**
	 * @param DataProviderStore $dataProviderStore
	 */
	public function __construct(
		private readonly DataProviderStore $dataProviderStore
	) {
	}

	/**
	 * @return Response
	 * @throws HttpException
	 */
	public function execute() {
		$body = $this->getValidatedBody();

		$provider = $this->dataProviderStore->getProvider( $body['provider'] );
		if ( !$provider ) {
			throw new HttpException( 'Provider not found', 404 );
		}

		try {
			$entity = $provider->getEntity( $body['entityData'], RequestContext::getMain()->getUser() );
			$data = $entity->jsonSerialize();
			$data['_provided_by'] = $provider->getName();
			return $this->getResponseFactory()->createJson( $data );
		} catch ( NoAuthException | EntityForbiddenException | EntityNotFoundException $e ) {
			throw new HttpException( $e->getMessage(), $e->getCode() );
		} catch ( \Exception $e ) {
			throw new HttpException( $e->getMessage(), 500 );
		}
	}

	public function getBodyParamSettings(): array {
		return [
			'entityData' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'provider' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}
}
