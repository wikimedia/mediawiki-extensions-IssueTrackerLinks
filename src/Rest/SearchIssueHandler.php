<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\IssueTrackerLinks\Auth\Exception\NoAuthException;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\SearchableBackendProvider;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderStore;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * This is only a placeholder showing off functionality - not used yet
 */
class SearchIssueHandler extends SimpleHandler {

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
		$params = $this->getValidatedParams();

		$provider = $this->dataProviderStore->getProvider( str_replace( '_', ' ', $params['provider'] ) );
		if ( !$provider ) {
			throw new HttpException( 'Provider not found', 404 );
		}
		if ( !( $provider->getBackend() instanceof SearchableBackendProvider ) ) {
			throw new HttpException( 'Provider does not support search', 400 );
		}

		try {
			$results = $provider->search( $params['query'], RequestContext::getMain()->getUser() );
		} catch ( NoAuthException $e ) {
			throw new HttpException( $e->getMessage(), $e->getCode() );
		} catch ( \Exception $e ) {
			throw new HttpException( $e->getMessage(), 500 );
		}

		return $this->getResponseFactory()->createJson( $results );
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'provider' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'query' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}
}
