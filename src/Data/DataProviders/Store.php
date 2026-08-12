<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Data\DataProviders;

use MediaWiki\Extension\IssueTrackerLinks\DataProviderStore;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\DataStore\NoWriterException;

class Store implements IStore {

	/**
	 * @param DataProviderStore $dataProviderStore
	 */
	public function __construct(
		private readonly DataProviderStore $dataProviderStore
	) {
	}

	/** @inheritDoc */
	public function getReader() {
		return new Reader( $this->dataProviderStore );
	}

	/** @inheritDoc */
	public function getWriter() {
		throw new NoWriterException();
	}
}
