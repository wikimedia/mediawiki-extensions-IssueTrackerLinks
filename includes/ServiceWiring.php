<?php

use MediaWiki\Extension\IssueTrackerLinks\PatternConfig;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'IssueTrackerLinks.PatternConfig' => static function ( MediaWikiServices $services ) {
		return new PatternConfig(
			$services->getTitleFactory(),
			$services->getWikiPageFactory(),
			$services->getService( 'IssueTrackerLinks._Logger' )
		);
	},
	'IssueTrackerLinks._Logger' => static function () {
		return LoggerFactory::getInstance( 'IssueTrackerLinks' );
	},
];
