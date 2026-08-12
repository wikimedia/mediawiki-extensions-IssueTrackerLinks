<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Data\IssueLinks;

class Record extends \MWStake\MediaWiki\Component\DataStore\Record {
	public const KEY = 'key';
	public const LABEL = 'label';
	public const LABEL_PARSED = 'label_parsed';
	public const URL = 'url';
	public const DISPLAY_MASK = 'display_mask';
	public const DATA_PROVIDER = 'data_provider';
}
