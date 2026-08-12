<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Data\IssueLinks;

use MediaWiki\Extension\IssueTrackerLinks\PatternConfig;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\DataStore\NoWriterException;

class Store implements IStore {

	/**
	 * @param PatternConfig $patternConfig
	 */
	public function __construct(
		private readonly PatternConfig $patternConfig
	) {
	}

	/** @inheritDoc */
	public function getReader() {
		return new Reader( $this->patternConfig );
	}

	/** @inheritDoc */
	public function getWriter() {
		throw new NoWriterException();
	}
}
