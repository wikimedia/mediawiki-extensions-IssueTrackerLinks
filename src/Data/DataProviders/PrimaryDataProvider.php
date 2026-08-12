<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Data\DataProviders;

use MediaWiki\Extension\IssueTrackerLinks\DataProviderStore;
use MWStake\MediaWiki\Component\DataStore\IPrimaryDataProvider;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;

class PrimaryDataProvider implements IPrimaryDataProvider {

	/**
	 * @param DataProviderStore $dataProviderStore
	 */
	public function __construct(
		private readonly DataProviderStore $dataProviderStore
	) {
	}

	/**
	 * @param ReaderParams $params
	 * @return array|\MWStake\MediaWiki\Component\DataStore\Record[]
	 */
	public function makeData( $params ) {
		$providers = $this->dataProviderStore->listProviders();

		$data = [];
		foreach ( $providers as $name => $type ) {
			$rowData = [
				Record::NAME => $name,
				Record::TYPE => $type,
			];
			$data[] = new Record( (object)$rowData );
		}

		return $data;
	}
}
