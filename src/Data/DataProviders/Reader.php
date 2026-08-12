<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Data\DataProviders;

use MediaWiki\Extension\IssueTrackerLinks\DataProviderStore;

class Reader extends \MWStake\MediaWiki\Component\DataStore\Reader {

	/**
	 * @param DataProviderStore $dataProviderStore
	 */
	public function __construct(
		private readonly DataProviderStore $dataProviderStore
	) {
		parent::__construct();
	}

	/**
	 * @param array $params
	 * @return PrimaryDataProvider
	 */
	protected function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider( $this->dataProviderStore );
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
