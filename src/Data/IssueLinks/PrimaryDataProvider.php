<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Data\IssueLinks;

use MediaWiki\Extension\IssueTrackerLinks\PatternConfig;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\DataStore\IPrimaryDataProvider;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;

class PrimaryDataProvider implements IPrimaryDataProvider {

	/**
	 * @param PatternConfig $patternConfig
	 */
	public function __construct(
		private readonly PatternConfig $patternConfig
	) {
	}

	/**
	 * @param ReaderParams $params
	 * @return array|\MWStake\MediaWiki\Component\DataStore\Record[]
	 */
	public function makeData( $params ) {
		$config = $this->patternConfig->getFullConfig();

		$data = [];
		foreach ( $config as $key => $item ) {
			$label = $item['label'] ?? '';
			$labelDisplay = $label;
			if ( $label && Message::newFromKey( $label )->exists() ) {
				$labelDisplay = Message::newFromKey( $label )->text();
			}
			$rowData = [
				Record::KEY => $key,
				Record::LABEL => $label,
				Record::LABEL_PARSED => $labelDisplay,
				Record::URL => $item['url'] ?? '',
				Record::DISPLAY_MASK => $item['display-mask'] ?? '',
				Record::DATA_PROVIDER => $item['data-provider'] ?? '',
			];
			$data[] = new Record( (object)$rowData );
		}

		return $data;
	}
}
