<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Data\IssueLinks;

use MediaWiki\Extension\IssueTrackerLinks\PatternConfig;

class Reader extends \MWStake\MediaWiki\Component\DataStore\Reader {

	/**
	 * @param PatternConfig $patternConfig
	 */
	public function __construct(
		private readonly PatternConfig $patternConfig
	) {
		parent::__construct();
	}

	/**
	 * @param array $params
	 * @return PrimaryDataProvider
	 */
	protected function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider( $this->patternConfig );
	}

	/**
	 * @inheritDoc
	 */
	protected function makeSecondaryDataProvider() {
		return null;
	}

	/**
	 * @return Schema
	 */
	public function getSchema() {
		return new Schema();
	}
}
