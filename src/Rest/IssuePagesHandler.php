<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\IssueTrackerLinks\LinkRegistry;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class IssuePagesHandler extends SimpleHandler {

	/**
	 * @param LinkRegistry $linkRegistry
	 */
	public function __construct(
		private readonly LinkRegistry $linkRegistry
	) {
	}

	/**
	 * @return Response
	 * @throws HttpException
	 */
	public function execute() {
		$params = $this->getValidatedParams();
		$issueParamsJson = $params['params'] ?? null;
		$issueParams = [];
		if ( is_string( $issueParamsJson ) ) {
			$issueParams = json_decode( $issueParamsJson, true );
		}

		$requester = RequestContext::getMain()->getUser();

		if ( $params['selector_type'] === 'link_type' ) {
			return $this->getResponseFactory()->createJson(
				$this->linkRegistry->getPagesForIssueType( $params['selector_id'], $issueParams, $requester )
			);
		} elseif ( $params['selector_type'] === 'provider' ) {
			return $this->getResponseFactory()->createJson(
				$this->linkRegistry->getPagesForDataProvider( $params['selector_id'], $issueParams, $requester )
			);
		}

		throw new HttpException( 'Invalid selector type', 400 );
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'selector_type' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'selector_id' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'params' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			]
		];
	}

	/**
	 * @return true
	 */
	public function needsReadAccess() {
		return true;
	}
}
